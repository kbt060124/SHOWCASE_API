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
                'content_type' => $request->header('Content-Type'),
                'all_inputs' => $request->all()
            ]);

            // バリデーション
            $request->validate([
                'filenames' => 'required|array|min:1|max:5',
                'filenames.*' => 'required|string',
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

            // 指定されたファイル名の画像を追加
            foreach ($request->filenames as $filename) {
                $tempPath = storage_path('app/temp/' . $filename);

                if (!file_exists($tempPath)) {
                    throw new \Exception("ファイルが見つかりません: {$filename}");
                }

                Log::info('Adding file to request', [
                    'filename' => $filename,
                    'path' => $tempPath
                ]);

                $multipart[] = [
                    'name' => 'images',
                    'contents' => fopen($tempPath, 'r'),
                    'filename' => $filename,
                    'headers' => [
                        'Content-Type' => 'image/png'
                    ]
                ];
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

            // 一時ファイルの削除
            foreach ($request->filenames as $filename) {
                $tempPath = storage_path('app/temp/' . $filename);
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                    Log::info('一時ファイルを削除しました', [
                        'filename' => $filename,
                        'path' => $tempPath
                    ]);
                }
            }

            // subscription_keyとuuidの両方を返す
            return response()->json([
                'taskId' => $result['uuid'],
                'subscriptionKey' => $result['jobs']['subscription_key'],
                'message' => '3Dモデル生成タスクを開始しました'
            ]);
        } catch (\Exception $e) {
            // エラー時も一時ファイルを削除
            if (isset($request->filenames)) {
                foreach ($request->filenames as $filename) {
                    $tempPath = storage_path('app/temp/' . $filename);
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                        Log::info('エラー発生時の一時ファイル削除', [
                            'filename' => $filename,
                            'path' => $tempPath
                        ]);
                    }
                }
            }

            Log::error('Rodin API Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 画像からオブジェクトが判定不可だった場合、カスタムエラーを返す
            if ($e->getMessage() === 'No object found on the image. Please check your input and try again.') {
                return response()->json([
                    'error' => 'No objects detected in the image',
                    'message' => $e->getMessage()
                ], 500);
            }

            // その他のエラーの場合は通常のエラーメッセージを返す
            return response()->json([
                'error' => 'Failed to start 3D model generation',
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
                        // 処理継続中のためレスポンスはエラーにしない
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
                'error' => 'Failed to check status',
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
                    'error' => 'File not found'
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
                'error' => 'Failed to preview',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 生成されたGLBファイルを削除する
     */
    private function deleteGeneratedModel($filename)
    {
        try {
            $path = "public/generated_models/{$filename}";
            if (Storage::exists($path)) {
                Storage::delete($path);
                Log::info('Generated model deleted', [
                    'filename' => $filename,
                    'path' => $path
                ]);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to delete generated model', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);
            return false;
        }
    }

    /**
     * 3Dモデルを保存する
     */
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

                    // Storageに保存されたGLBファイルを削除
                    $this->deleteGeneratedModel($request->filename);

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
                        'message' => 'Failed to upload 3d model',
                        'error' => $e->getMessage()
                    ], 500);
                }
            }

            Log::error('必要なサムネイルファイルが見つかりません');
            return response()->json(['error' => 'Thumbnail not found'], 400);
        } catch (\Exception $e) {
            Log::error('バリデーションエラー', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Invalid input values',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 生成されたGLBファイルを削除するAPI
     */
    public function deleteGeneratedModelApi(Request $request)
    {
        try {
            $request->validate([
                'filename' => 'required|string'
            ]);

            $result = $this->deleteGeneratedModel($request->filename);

            if ($result) {
                return response()->json([
                    'message' => 'GLBファイルを削除しました',
                    'status' => 'success'
                ]);
            } else {
                return response()->json([
                    'message' => '3D model file not found',
                    'error' => '3D model file not found'
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('GLBファイル削除API エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to delete 3D model',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 画像の背景を削除するAPI
     */
    public function removeBackground(Request $request)
    {
        try {
            // バリデーション
            $request->validate([
                'images' => 'required|array|min:1|max:5',
                'images.*' => 'required|file|image|max:10240', // 各画像10MB制限
            ]);

            $processedImages = [];

            foreach ($request->file('images') as $index => $image) {
                try {
                    // 処理状況をログに記録
                    Log::info('画像処理開始', [
                        'index' => $index + 1,
                        'total' => count($request->file('images')),
                        'original_name' => $image->getClientOriginalName()
                    ]);

                    // PhotoRoom APIの設定
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
                                'contents' => fopen($image->getRealPath(), 'r'),
                                'filename' => $image->getClientOriginalName()
                            ]
                        ]
                    ]);

                    // 一時ファイルとして保存
                    $tempPath = storage_path('app/temp/' . uniqid() . '.png');
                    if (!file_exists(dirname($tempPath))) {
                        mkdir(dirname($tempPath), 0777, true);
                    }

                    file_put_contents($tempPath, $response->getBody());

                    // 処理済み画像の情報を保存
                    $processedName = basename($tempPath);
                    $processedImages[] = $processedName;

                    Log::info('画像処理完了', [
                        'index' => $index + 1,
                        'processed_name' => $processedName,
                        'path' => $tempPath
                    ]);
                } catch (\Exception $e) {
                    Log::error('画像処理エラー', [
                        'index' => $index + 1,
                        'error' => $e->getMessage()
                    ]);

                    return response()->json([
                        'error' => 'Failed to remove image background',
                        'message' => $e->getMessage(),
                        'failed_image' => $image->getClientOriginalName(),
                    ], 500);
                }
            }

            return response()->json([
                'message' => '背景削除処理が完了しました',
                'processed_images' => $processedImages,
                'status' => 'Removed'
            ]);
        } catch (\Exception $e) {
            Log::error('背景削除API エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to remove image background',
                'message' => $e->getMessage(),
                'status' => 'remove_error'
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
}
