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
                'images' => 'required|file|image|max:10240', // 10MB制限
                'tier' => 'required|string|in:Sketch,Regular'
            ]);

            // Rodin APIの設定
            $client = new Client();
            $rodinApiKey = env('RODIN_API_KEY');
            $rodinEndpoint = 'https://hyperhuman.deemos.com/api/v2/rodin';

            // 画像ファイルの準備
            $image = $request->file('images');

            Log::info('Preparing image file', [
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => $image->getMimeType(),
                'size' => $image->getSize()
            ]);

            // Rodin APIにリクエスト
            $response = $client->post($rodinEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $rodinApiKey,
                ],
                'multipart' => [
                    [
                        'name' => 'images',
                        'contents' => fopen($image->getRealPath(), 'r'),
                        'filename' => $image->getClientOriginalName(),
                        'headers' => [
                            'Content-Type' => $image->getMimeType()
                        ]
                    ],
                    [
                        'name' => 'tier',
                        'contents' => $request->input('tier', 'Sketch')
                    ]
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('Rodin API Response', [
                'response' => $result
            ]);

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

    public function checkStatus(Request $request)
    {
        try {
            $request->validate([
                'subscriptionKey' => 'required|string',
            ]);

            $client = new Client();
            $rodinApiKey = env('RODIN_API_KEY');
            $statusEndpoint = 'https://hyperhuman.deemos.com/api/v2/status';

            // リクエストの内容をログ出力
            Log::info('Status check request', [
                'subscriptionKey' => $request->subscriptionKey
            ]);

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

            // レスポンスの内容をログ出力
            Log::info('Rodin status response', [
                'response' => $result
            ]);

            // jobsの配列から最初のジョブのステータスを取得
            if (isset($result['jobs']) && is_array($result['jobs']) && count($result['jobs']) > 0) {
                $jobStatus = $result['jobs'][0]['status'] ?? 'Unknown';
                $jobMessage = $result['jobs'][0]['message'] ?? null;

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
