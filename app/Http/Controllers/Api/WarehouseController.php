<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        // ログを出力
        Log::info('Upload request received');
        Log::info('Request headers', ['headers' => $request->headers->all()]);
        Log::info('Request all', ['request' => $request->all()]);
        Log::info('Request files', ['files' => $request->file()]);

        // POSTされてきたuser_id, jsonファイル, filesをそれぞれ変数に代入
        $userId = $request->user_id;
        $jsonFile = $request->json;
        $items = $request->file('files');

        // S3の保存先ディレクトリ
        $s3WarehousesRootPath = 'warehouses/';

        // 1ユーザーが持つ1つのjsonファイルを保存
        // ファイル名を含まない保存先パス
        $path = $s3WarehousesRootPath . $userId .'/';
        // エラーメッセージ
        $errorMessage = 'jsonファイルのアップロードに失敗しました';
        // 保存 & 保存先URLを取得
        $url = self::saveFile($jsonFile, $path, $errorMessage);

        // filesにある複数アイテムを保存
        foreach($items as $item){

            // 一意のwarehouse_id(アイテムのID)を生成
            $uniqueWarehouseId = Str::uuid();

            // 1アイテムが持つ複数ファイルを保存
            foreach($item as $file){
                
                // fbxファイル、サムネイル用の.pngファイルを保存
                if (!is_array($file)) {
                    // ファイル名を含まない保存先パス
                    $path = $s3WarehousesRootPath . $userId .'/'. $uniqueWarehouseId .'/';
                    // エラーメッセージ
                    $errorMessage = 'ファイルのアップロードに失敗しました';
                    // 保存 & 保存先URLを取得
                    $url = self::saveFile($file, $path, $errorMessage);
                }

                // textures用のファイル群を複数保存
                else {
                    foreach($file as $texture){
                        // ファイル名を含まない保存先パス
                        $path = $s3WarehousesRootPath . $userId .'/'. $uniqueWarehouseId .'/'. 'textures/';
                        // エラーメッセージ
                        $errorMessage = 'textures用ファイルのアップロードに失敗しました';
                        // 保存 & 保存先URLを取得
                        $url = self::saveFile($texture, $path, $errorMessage);
                    }
                }
            }
        }

        Log::error('No file found in the request');
        return response()->json(['error' => 'ファイルが見つかりません'], 400);
    }

    // ファイルを保存
    private function saveFile($file, $path, $errorMessage) {
        try {
            self::logFileReceived($file);   
            $filePath = self::generateFilePath($file, $path);
            $fileContents = self::getFileContentes($file);
            $mimeType = self::getMimeType($file);
            $result = self::saveToS3($filePath, $fileContents, $mimeType);

            if (!$result) {
                throw new \Exception($errorMessage);
            }

            $url = self::getFileStoredPath($filePath);

            return $url;
        } catch (\Exception $e) {
            self::errorLog($e, $errorMessage);
        }
    }

    // ファイルに関するログを出力
    private function logFileReceived($file) {
        Log::info('File received', [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension()
        ]);
    }

    // ファイル保存先のパスを生成
    private function generateFilePath($file, $path) {
        // ファイル名を取得
        $originalFileName = $file->getClientOriginalName();
        // パスを生成
        $filePath = $path . $originalFileName;
        
        return $filePath;
    }

    // ファイルの内容を取得
    private function getFileContentes($file) {
        return $fileContents = file_get_contents($file->getRealPath());
    }

    // ファイルのMIMEタイプを取得
    private function getMimeType($file) {
        return $mimeType = $file->getMimeType();
    }

    // AWS S3に保存
    private function saveToS3($path, $fileContents, $mineType) {
        $result = Storage::disk('s3')->put(
            $path,
            $fileContents,
            ['ContentType' => $mineType]
        );
        
        return $result;
    }

    // ファイル保存先パスを取得
    private function getFileStoredPath($path) {
        $url = Storage::disk('s3')->url($path);
        Log::info('File uploaded successfully', ['url' => $url]);

        return $url;
    }

    // エラーを出力
    private function errorLog($e, $errorMessage) {
        Log::error('File upload failed', ['error' => $e->getMessage()]);

        return response()->json(['error' => $errorMessage], 500);
    }

    /**
     * Display the specified resource.
     */
    public function show(Warehouse $warehouse)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Warehouse $warehouse)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Warehouse $warehouse)
    {
        //
    }

    public function download($warehouse_id)
    {
        //
    }
}
