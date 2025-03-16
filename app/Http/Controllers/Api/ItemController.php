<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use GuzzleHttp\Client;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($user_id)
    {
        $items = Item::where('user_id', $user_id)->get();
        return response()->json($items);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        try {
            // リクエストの内容をログ出力
            Log::info('Received request', [
                'has_file' => $request->hasFile('images'),
                'content_type' => $request->header('Content-Type'),
                'all_files' => $request->allFiles(),
                'all_inputs' => $request->all()
            ]);

            // 画像のバリデーション
            $request->validate([
                'images' => 'required|array|min:1|max:5',
                'images.*' => 'required|file|image|max:10240', // 各画像10MB制限
                'tier' => 'required|string|in:Sketch,Regular'
            ]);

            // Rodin APIの設定
            $client = new Client();
            $rodinApiKey = env('RODIN_API_KEY');
            $rodinEndpoint = 'https://hyperhuman.deemos.com/api/v2/rodin';

            // multipartデータの準備
            $multipart = [
                [
                    'name' => 'condition_mode',
                    'contents' => 'concat'
                ],
                [
                    'name' => 'tier',
                    'contents' => $request->input('tier', 'Sketch')
                ],
                [
                    'name' => 'geometry_file_format',
                    'contents' => 'glb'
                ]
            ];

            // 画像ファイルの追加
            foreach ($request->file('images') as $image) {
                Log::info('Preparing image file', [
                    'original_name' => $image->getClientOriginalName(),
                    'mime_type' => $image->getMimeType(),
                    'size' => $image->getSize()
                ]);

                // 背景削除処理
                $processedImage = $this->removeBackground($image);

                $multipart[] = [
                    'name' => 'images',
                    'contents' => fopen($processedImage->getRealPath(), 'r'),
                    'filename' => $processedImage->getClientOriginalName(),
                    'headers' => [
                        'Content-Type' => $processedImage->getMimeType()
                    ]
                ];

                // 一時ファイルを削除
                unlink($processedImage->getRealPath());
            }

            // Rodin APIにリクエスト
            $response = $client->post($rodinEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $rodinApiKey,
                ],
                'multipart' => $multipart
            ]);

            $responseBody = $response->getBody()->getContents();
            Log::info('Raw Rodin API Response', [
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $responseBody
            ]);

            $result = json_decode($responseBody, true);

            Log::info('Parsed Rodin API Response', [
                'response' => $result
            ]);

            // エラーチェック
            if (isset($result['error']) && $result['error'] !== null) {
                throw new \Exception($result['message'] ?? 'Unknown error from Rodin API');
            }

            // レスポンスの構造チェック
            if (!isset($result['uuid']) || !isset($result['jobs']) || !isset($result['jobs']['subscription_key'])) {
                Log::error('Invalid Rodin API response structure', [
                    'response' => $result
                ]);
                throw new \Exception('Rodin APIからの予期しない応答形式です');
            }

            // subscription_keyとuuidの両方を返す
            return response()->json([
                'taskId' => $result['uuid'],
                'subscriptionKey' => $result['jobs']['subscription_key'],
                'message' => '3Dモデル生成タスクを開始しました'
            ]);
        } catch (\Exception $e) {
            Log::error('Rodin API Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => '3Dモデル生成の開始に失敗しました',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rodinのタスクのステータスを確認する
     */
    public function checkStatus(Request $request)
    {
        try {
            $request->validate([
                'subscriptionKey' => 'required|string',
                'taskId' => 'required|string',
            ]);

            $client = new Client();
            $rodinApiKey = env('RODIN_API_KEY');
            $statusEndpoint = 'https://hyperhuman.deemos.com/api/v2/status';

            // ステータスチェック
            $response = $client->post($statusEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $rodinApiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'subscription_key' => $request->subscriptionKey
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            Log::info('Rodin status response', ['response' => $result]);

            if (isset($result['jobs']) && is_array($result['jobs']) && count($result['jobs']) > 0) {
                $jobStatus = $result['jobs'][0]['status'] ?? 'Unknown';
                $jobMessage = $result['jobs'][0]['message'] ?? null;

                // ステータスがDoneの場合、ファイルの存在を確認
                if ($jobStatus === 'Done') {
                    try {
                        // ダウンロードURLを取得
                        $downloadEndpoint = 'https://hyperhuman.deemos.com/api/v2/download';
                        $downloadResponse = $client->post($downloadEndpoint, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $rodinApiKey,
                                'Content-Type' => 'application/json'
                            ],
                            'json' => [
                                'task_uuid' => $request->taskId
                            ]
                        ]);

                        $downloadResult = json_decode($downloadResponse->getBody()->getContents(), true);

                        if (isset($downloadResult['list']) && is_array($downloadResult['list'])) {
                            $glbFiles = [];
                            foreach ($downloadResult['list'] as $file) {
                                if (str_ends_with(strtolower($file['name']), '.glb')) {
                                    // GLBファイルをStorageに保存
                                    $savedFile = $this->saveGlbToStorage($file['url'], $file['name'], $request->taskId);
                                    if ($savedFile) {
                                        $glbFiles[] = $savedFile;
                                    }
                                }
                            }

                            if (!empty($glbFiles)) {
                                return response()->json([
                                    'status' => 'Done',
                                    'message' => 'ファイルの生成が完了しました',
                                    'downloadUrls' => $glbFiles
                                ]);
                            }
                        }

                        // ファイルが見つからない場合は処理中として扱う
                        return response()->json([
                            'status' => 'Processing',
                            'message' => 'ファイルを生成中です'
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Download check error', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        return response()->json([
                            'status' => 'Processing',
                            'message' => 'ファイルの確認中にエラーが発生しました'
                        ]);
                    }
                }

                return response()->json([
                    'status' => $jobStatus,
                    'message' => $jobMessage
                ]);
            }

            return response()->json([
                'status' => 'Unknown',
                'message' => 'ステータス情報が取得できませんでした'
            ]);
        } catch (\Exception $e) {
            Log::error('Status Check Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'ステータスチェックに失敗しました',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GLBファイルをStorageに保存する
     */
    private function saveGlbToStorage($url, $filename, $taskId)
    {
        try {
            $client = new Client();
            $response = $client->get($url, [
                'stream' => true,
                'headers' => [
                    'Accept' => 'application/octet-stream'
                ]
            ]);

            // ファイル名の重複を避けるため、タスクIDをプレフィックスとして付与
            $uniqueFilename = "{$taskId}_{$filename}";
            // 保存先のパスを生成（public/generated_models直下に保存）
            $storagePath = "public/generated_models/{$uniqueFilename}";

            // ストリームとしてファイルを保存
            Storage::put($storagePath, $response->getBody()->getContents());

            return [
                'name' => $filename,
                'path' => $storagePath,
                'url' => Storage::url($storagePath)
            ];
        } catch (\Exception $e) {
            Log::error('GLB file save error', [
                'error' => $e->getMessage(),
                'url' => $url,
                'filename' => $filename,
                'taskId' => $taskId
            ]);
            return null;
        }
    }

    /**
     * GLBファイルをバイナリデータとして返す
     */
    public function previewModel($filename)
    {
        try {
            $path = "public/generated_models/{$filename}";

            if (!Storage::exists($path)) {
                return response()->json([
                    'error' => 'ファイルが見つかりません'
                ], 404);
            }

            $content = Storage::get($path);

            return response($content)
                ->header('Content-Type', 'model/gltf-binary')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
        } catch (\Exception $e) {
            Log::error('Preview Error', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);

            return response()->json([
                'error' => 'プレビューの取得に失敗しました',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        try {
            $validated = $request->validate([
                'file' => 'required|file|max:100000',
                'user_id' => 'required|integer',
                'name' => 'required|string|max:255',
                'thumbnail' => 'required|image|max:5120',
            ]);
        } catch (ValidationException $e) {
            Log::error('バリデーションエラー', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'files' => $request->files->all()
            ]);
            throw $e;
        }

        // 詳細なリクエスト情報のログ
        Log::info('アップロードリクエスト詳細', [
            'headers' => $request->headers->all(),
            'content_type' => $request->header('Content-Type'),
            'has_file' => $request->hasFile('file'),
            'has_thumbnail' => $request->hasFile('thumbnail'),
        ]);

        if ($request->hasFile('file') && $request->hasFile('thumbnail')) {
            try {
                $glbFile = $request->file('file');
                $thumbnailFile = $request->file('thumbnail');

                // GLBファイルの検証
                $this->validateGlbFile($glbFile);

                // ファイル名を取得
                $glbStoredFileName = $glbFile->getClientOriginalName();
                $thumbnailStoredFileName = $thumbnailFile->getClientOriginalName();

                // GLBファイルのサイズを取得（バイト単位）
                $fileSize = $glbFile->getSize();

                // まず、データベースにアイテムを保存してIDを取得
                $item = Item::create([
                    'user_id' => $request->user_id,
                    'name' => $request->name,
                    'memo' => $request->memo,
                    'totalsize' => $fileSize,
                    'thumbnail' => $thumbnailStoredFileName,
                    'filename' => $glbStoredFileName
                ]);

                // S3の保存先ディレクトリ
                $s3ItemsRootPath = 'warehouse/';
                // ファイル名を含まない保存先パス
                $path = $s3ItemsRootPath . $request->user_id . '/' . $item->id . '/';

                // GLBファイルの保存
                $glbErrorMessage = 'GLBファイルのアップロードに失敗しました';
                $glbStoredFilePath = $this->generateFilePath($glbFile, $path);
                $glbStoredFileName = basename($glbStoredFilePath);
                $glbUrl = $this->saveFile($glbFile, $path, $glbErrorMessage);

                // サムネイル画像の保存
                $thumbnailErrorMessage = 'サムネイル画像のアップロードに失敗しました';
                $thumbnailStoredFilePath = $this->generateFilePath($thumbnailFile, $path);
                $thumbnailStoredFileName = basename($thumbnailStoredFilePath);
                $thumbnailUrl = $this->saveFile($thumbnailFile, $path, $thumbnailErrorMessage);


                return response()->json([
                    'message' => 'アップロード成功',
                    'item' => $item
                ], 201);
            } catch (\Exception $e) {
                // エラーが発生した場合、作成したアイテムを削除
                if (isset($item)) {
                    $item->delete();
                }

                Log::error('ファイルアップロードエラー詳細', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'message' => 'アップロード失敗',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        Log::error('必要なファイルが見つかりません');
        return response()->json(['error' => '必要なファイルが見つかりません'], 400);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $item = Item::findOrFail($id);
            Log::info('更新前のアイテム情報', ['item' => $item]);

            // バリデーションルールを設定
            $rules = [
                'name' => 'nullable|string|max:255',
                'memo' => 'nullable|string',
                'thumbnail' => 'nullable|image|max:5120',
            ];

            $validated = $request->validate($rules);

            $item = Item::findOrFail($id);
            Log::info('更新前のアイテム情報', ['item' => $item->toArray()]);

            // サムネイル画像が提供された場合の処理
            if ($request->hasFile('thumbnail')) {
                Log::info('サムネイル画像更新処理開始', [
                    'original_name' => $request->file('thumbnail')->getClientOriginalName()
                ]);

                $thumbnailFile = $request->file('thumbnail');

                // S3の保存先ディレクトリ
                $s3ItemsRootPath = 'warehouse/';
                $path = $s3ItemsRootPath . $item->user_id . '/' . $item->id . '/';

                // 古いサムネイル画像を削除
                $oldThumbnailPath = $path . $item->thumbnail;
                Log::info('古いサムネイル削除処理', ['old_path' => $oldThumbnailPath]);

                if (Storage::disk('s3')->exists($oldThumbnailPath)) {
                    Storage::disk('s3')->delete($oldThumbnailPath);
                    Log::info('古いサムネイル削除成功');
                }

                // 新しいサムネイル画像を保存
                $thumbnailErrorMessage = 'サムネイル画像のアップロードに失敗しました';
                $thumbnailStoredFilePath = $this->generateFilePath($thumbnailFile, $path);
                $thumbnailStoredFileName = basename($thumbnailStoredFilePath);
                $thumbnailUrl = $this->saveFile($thumbnailFile, $path, $thumbnailErrorMessage);

                $item->thumbnail = $thumbnailStoredFileName;
                Log::info('新しいサムネイル保存完了', ['new_thumbnail' => $thumbnailStoredFileName]);
            }

            // 名前とメモの更新
            if ($request->has('name')) {
                $item->name = $request->name;
            }

            if ($request->has('memo')) {
                $item->memo = $request->memo;
            }

            $item->save();
            Log::info('アイテム更新完了', ['item' => $item->toArray()]);

            return response()->json([
                'message' => 'アイテム情報を更新しました',
                'item' => $item
            ]);
        } catch (\Exception $e) {
            Log::error('アイテム更新エラー', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'アイテムの更新に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $item = Item::findOrFail($id);

            // トランザクション開始
            DB::beginTransaction();

            try {
                // 中間テーブルの関連データを削除
                $item->rooms()->detach();

                // S3からフォルダとその中身を全て削除
                $s3ItemsRootPath = 'warehouse/';
                $itemFolderPath = $s3ItemsRootPath . $item->user_id . '/' . $item->id;
                $files = Storage::disk('s3')->allFiles($itemFolderPath);
                Storage::disk('s3')->delete($files);

                // アイテムを削除
                $item->delete();

                // トランザクションコミット
                DB::commit();

                Log::info('アイテム削除完了', [
                    'item_id' => $id,
                    'deleted_files' => $files
                ]);

                return response()->json([
                    'message' => 'アイテムを削除しました'
                ], 200);
            } catch (\Exception $e) {
                // エラー発生時はロールバック
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('アイテム削除エラー', [
                'error' => $e->getMessage(),
                'item_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'アイテムの削除に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ファイルを保存
     */
    private function saveFile($file, $path, $errorMessage)
    {
        try {
            $this->logFileReceived($file);
            $filePath = $this->generateFilePath($file, $path);
            $fileContents = $this->getFileContents($file);
            $mimeType = $this->getMimeType($file);
            $result = $this->saveToS3($filePath, $fileContents, $mimeType);

            if (!$result) {
                throw new \Exception($errorMessage);
            }

            $url = $this->getFileStoredPath($filePath);

            return $url;
        } catch (\Exception $e) {
            return $this->errorLog($e, $errorMessage);
        }
    }

    /**
     * ファイルに関するログを出力
     */
    private function logFileReceived($file)
    {
        Log::info('File received', [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension()
        ]);
    }

    /**
     * ファイル保存先のパスを生成
     */
    private function generateFilePath($file, $path)
    {
        $originalFileName = $file->getClientOriginalName();
        return $path . $originalFileName;
    }

    /**
     * ファイルの内容を取得
     */
    private function getFileContents($file)
    {
        return file_get_contents($file->getRealPath());
    }

    /**
     * ファイルのMIMEタイプを取得
     */
    private function getMimeType($file)
    {
        return $file->getMimeType();
    }

    /**
     * AWS S3に保存
     */
    private function saveToS3($path, $fileContents, $mimeType)
    {
        return Storage::disk('s3')->put(
            $path,
            $fileContents,
            ['ContentType' => $mimeType]
        );
    }

    /**
     * ファイル保存先パスを取得
     */
    private function getFileStoredPath($path)
    {
        $url = Storage::disk('s3')->url($path);
        Log::info('File uploaded successfully', ['url' => $url]);
        return $url;
    }

    /**
     * エラーを出力
     */
    private function errorLog($e, $errorMessage)
    {
        Log::error('File upload failed', ['error' => $e->getMessage()]);
        throw new \Exception($errorMessage);
    }

    private function validateGlbFile($file)
    {
        // ファイル名の拡張子を確認
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'glb') {
            throw ValidationException::withMessages([
                'file' => ['ファイルの形式はGLBである必要があります。']
            ]);
        }

        // ファイルの先頭バイトを確認（GLBファイルのマジックナンバー）
        $handle = fopen($file->getRealPath(), 'rb');
        $header = fread($handle, 4);
        fclose($handle);

        // GLBファイルのマジックナンバーを確認（0x46546C67）
        if (bin2hex($header) !== '676c5446') {
            throw ValidationException::withMessages([
                'file' => ['無効なGLBファイル形式です。']
            ]);
        }

        return true;
    }

    public function rodinStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'filename' => 'required|string',
                'user_id' => 'required|integer',
                'name' => 'required|string|max:255',
                'thumbnail' => 'required|image|max:5120',
            ]);

            if ($request->hasFile('thumbnail')) {
                try {
                    // storageからglbファイルを取得
                    $glbPath = Storage::disk('local')->path('public/generated_models/' . $request->filename);

                    if (!file_exists($glbPath)) {
                        throw new \Exception('GLBファイルが見つかりません');
                    }

                    // GLBファイルをアップロード用のファイルオブジェクトとして作成
                    $glbFile = new \Illuminate\Http\UploadedFile(
                        $glbPath,
                        $request->filename,
                        'application/octet-stream',
                        null,
                        true
                    );

                    $thumbnailFile = $request->file('thumbnail');

                    // GLBファイルの検証
                    $this->validateGlbFile($glbFile);

                    // GLBファイルのサイズを取得
                    $fileSize = filesize($glbPath);

                    // データベースにアイテムを保存
                    $item = Item::create([
                        'user_id' => $request->user_id,
                        'name' => $request->name,
                        'memo' => $request->memo,
                        'totalsize' => $fileSize,
                        'thumbnail' => $thumbnailFile->getClientOriginalName(),
                        'filename' => $request->filename
                    ]);

                    // S3の保存先ディレクトリ
                    $s3ItemsRootPath = 'warehouse/';
                    $path = $s3ItemsRootPath . $request->user_id . '/' . $item->id . '/';

                    // GLBファイルの保存
                    $glbErrorMessage = 'GLBファイルのアップロードに失敗しました';
                    $glbStoredFilePath = $this->generateFilePath($glbFile, $path);
                    $glbUrl = $this->saveFile($glbFile, $path, $glbErrorMessage);

                    // サムネイル画像の保存
                    $thumbnailErrorMessage = 'サムネイル画像のアップロードに失敗しました';
                    $thumbnailStoredFilePath = $this->generateFilePath($thumbnailFile, $path);
                    $thumbnailUrl = $this->saveFile($thumbnailFile, $path, $thumbnailErrorMessage);

                    return response()->json([
                        'message' => 'アップロード成功',
                        'item' => $item
                    ], 201);
                } catch (\Exception $e) {
                    if (isset($item)) {
                        $item->delete();
                    }

                    Log::error('ファイルアップロードエラー詳細', [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return response()->json([
                        'message' => 'アップロード失敗',
                        'error' => $e->getMessage()
                    ], 500);
                }
            }

            Log::error('必要なファイルが見つかりません');
            return response()->json(['error' => '必要なファイルが見つかりません'], 400);
        } catch (\Exception $e) {
            Log::error('バリデーションエラー', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            throw $e;
        }
    }

    private function removeBackground($imageFile)
    {
        try {
            $client = new Client();
            $apiKey = env('PHOTOROOM_API_KEY');
            $endpoint = 'https://sdk.photoroom.com/v1/segment';

            // multipartリクエストの準備
            $response = $client->post($endpoint, [
                'headers' => [
                    'Accept' => 'image/png, application/json',
                    'x-api-key' => $apiKey
                ],
                'multipart' => [
                    [
                        'name' => 'image_file',
                        'contents' => fopen($imageFile->getRealPath(), 'r'),
                        'filename' => $imageFile->getClientOriginalName()
                    ]
                ]
            ]);

            // 一時ファイルとして保存
            $tempPath = storage_path('app/temp/' . uniqid() . '.png');
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0777, true);
            }

            file_put_contents($tempPath, $response->getBody());

            // 処理された画像をアップロードファイルとして返す
            return new \Illuminate\Http\UploadedFile(
                $tempPath,
                pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME) . '_removed_bg.png',
                'image/png',
                null,
                true
            );

        } catch (\Exception $e) {
            Log::error('背景削除処理エラー', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw new \Exception('画像の背景削除処理に失敗しました: ' . $e->getMessage());
        }
    }
}
