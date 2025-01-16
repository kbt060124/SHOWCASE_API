<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:glb|max:50000',
            'user_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'thumbnail' => 'required|image|max:2048',
        ]);

        // ログを出力
        Log::info('Upload request received');
        Log::info('Request headers', ['headers' => $request->headers->all()]);
        Log::info('Request all', ['request' => $request->all()]);
        Log::info('Request file', ['file' => $request->file()]);

        if ($request->hasFile('file') && $request->hasFile('thumbnail')) {
            try {
                $glbFile = $request->file('file');
                $thumbnailFile = $request->file('thumbnail');

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
                    'filename' => $glbStoredFileName,
                    'totalsize' => $fileSize
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * ファイルを保存
     */
    private function saveFile($file, $path, $errorMessage)
    {
        try {
            $this->logFileReceived($file);
            $filePath = $this->generateFilePath($file, $path);
            $fileContents = $this->getFileContentes($file);
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
    private function getFileContentes($file)
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
}
