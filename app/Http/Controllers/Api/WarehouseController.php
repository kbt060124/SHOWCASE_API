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
        // dd($request->all());
        // dd($request->file('files'));
        // var_dump($request->file('files'));
        // dd($request->hasFile('files'));

        Log::info('Upload request received');
        Log::info('Request headers', ['headers' => $request->headers->all()]);
        Log::info('Request all', ['request' => $request->all()]);
        Log::info('Request files', ['files' => $request->file()]);

        // POSTされてきたuser_id, jsonファイル, filesをそれぞれ変数に代入
        $userId = $request->user_id;
        $jsonFile = $request->json;
        $items = $request->file('files');
        // dd($userId);
        // dd($json);
        // dd($items);

        // 変数:S3での保存先ディレクトリ
        $s3WarehousesRootPath = 'warehouses/';

        // 1ユーザーが持つ1つのjsonファイルを保存
        try {
            Log::info('json received', [$jsonFile]);

            // 拡張子を取得
            $extension = $jsonFile->getClientOriginalExtension();
            // 一意のファイル名を生成
            $uniqueFileName = Str::uuid() . '.' . $extension;

            // ファイルの内容を取得
            $fileContents = file_get_contents($jsonFile->getRealPath());

            // S3にファイルをアップロード
            // filesにあるファイル群を、同じフォルダ内に保存するためのパス
            $path = $s3WarehousesRootPath . $userId .'/'. $uniqueFileName;
            
            // 各ファイルのMIMEタイプを取得
            $mimeType = $jsonFile->getMimeType();

            // 保存実行
            $result = Storage::disk('s3')->put($path, $fileContents, [
                'ContentType' => $mimeType
            ]);

            if (!$result) {
                throw new \Exception('jsonファイルのアップロードに失敗しました');
            }

            // S3のURLを取得
            $url = Storage::disk('s3')->url($path);

            Log::info('File uploaded successfully', ['url' => $url]);
            // return response()->json(['url' => $url], 200);
        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'jsonファイルのアップロードに失敗しました'], 500);
        }

        // filesにある複数アイテムを保存
        foreach($items as $item){
            // dd($item);

            // 一意のwarehouse_id(アイテムのID)を生成
            $uniqueWarehouseId = Str::uuid();

            // 1アイテムが持つ複数ファイルを保存
            foreach($item as $file){

                // fbxファイル、サムネイル用の.pngファイルを保存
                if (!is_array($file)) {
                    try {
                        // dd($file);
                        
                        $extension = $file->getClientOriginalExtension();
                        Log::info('File received', [
                            'file_name' => $file->getClientOriginalName(),
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'extension' => $extension
                        ]);

                        // 一意のファイル名を生成
                        $uniqueFileName = Str::uuid() . '.' . $extension;

                        // ファイルの内容を取得
                        $fileContents = file_get_contents($file->getRealPath());

                        // S3にファイルをアップロード
                        // filesにあるファイル群を、同じフォルダ内に保存するためのパス
                        $path = $s3WarehousesRootPath . $userId .'/'. $uniqueWarehouseId .'/'. $uniqueFileName;
                        // $path = $s3WarehousesRootPath . $userId .'/'. $uniqueWarehouseId .'/'. 'textures/' . $uniqueFileName;
                        
                        // 各ファイルのMIMEタイプを取得
                        $mimeType = $file->getMimeType();

                        // 保存実行
                        $result = Storage::disk('s3')->put($path, $fileContents, [
                            'ContentType' => $mimeType
                        ]);

                        if (!$result) {
                            throw new \Exception('S3へのアップロードに失敗しました');
                        }

                        // S3のURLを取得
                        $url = Storage::disk('s3')->url($path);

                        Log::info('File uploaded successfully', ['url' => $url]);
                        // return response()->json(['url' => $url], 200);
                    } catch (\Exception $e) {
                        Log::error('File upload failed', ['error' => $e->getMessage()]);
                        return response()->json(['error' => 'ファイルのアップロードに失敗しました'], 500);
                    }
                }

                // textures用のファイル群を複数保存
                else {
                    foreach($file as $texture){
                        try {
                            // dd($texture);
                            
                            $extension = $texture->getClientOriginalExtension();
                            Log::info('File received', [
                                'file_name' => $texture->getClientOriginalName(),
                                'file_size' => $texture->getSize(),
                                'mime_type' => $texture->getMimeType(),
                                'extension' => $extension
                            ]);

                            // 一意のファイル名を生成
                            $uniqueFileName = Str::uuid() . '.' . $extension;

                            // ファイルの内容を取得
                            $fileContents = file_get_contents($texture->getRealPath());

                            // S3にファイルをアップロード
                            // filesにあるtextures用ファイル群を、同じフォルダ内に保存するためのパス
                            $path = $s3WarehousesRootPath . $userId .'/'. $uniqueWarehouseId .'/'. 'textures/' . $uniqueFileName;
                            
                            // 各ファイルのMIMEタイプを取得
                            $mimeType = $texture->getMimeType();

                            // 保存実行
                            $result = Storage::disk('s3')->put($path, $fileContents, [
                                'ContentType' => $mimeType
                            ]);

                            if (!$result) {
                                throw new \Exception('textures用ファイルのアップロードに失敗しました');
                            }

                            // S3のURLを取得
                            $url = Storage::disk('s3')->url($path);

                            Log::info('File uploaded successfully', ['url' => $url]);
                            // return response()->json(['url' => $url], 200);
                        } catch (\Exception $e) {
                            Log::error('File upload failed', ['error' => $e->getMessage()]);
                            return response()->json(['error' => 'textures用ファイルのアップロードに失敗しました'], 500);
                        }
                    }
                }
            }
        }

        // 
        Log::error('No file found in the request');
        return response()->json(['error' => 'ファイルが見つかりません'], 400);
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
