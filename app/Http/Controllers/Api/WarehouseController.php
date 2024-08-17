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
            Log::info('Upload request received');
            Log::info('Request headers', ['headers' => $request->headers->all()]);
            Log::info('Request all', ['request' => $request->all()]);
            Log::info('Request files', ['files' => $request->file()]);

        if ($request->hasFile('file')) {
                $file = $request->file('file');
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
            $path = 'uploads/' . $uniqueFileName;
            $fileContents = file_get_contents($file->getRealPath());

            Storage::disk('s3')->put($path, $fileContents, [
                'ContentType' => 'model/vnd.fbx'
            ]);

            // S3のURLを取得
            $url = Storage::disk('s3')->url($path);

            return response()->json(['url' => $url], 200);
        }

        Log::error('No file found in the request'); // ファイルが見つからない場合のログ
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
