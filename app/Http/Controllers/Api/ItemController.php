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
        ]);

        try {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();

            // デバッグログを追加
            Log::info('ファイルアップロード開始', [
                'fileName' => $fileName,
                'fileSize' => $file->getSize(),
                'mimeType' => $file->getMimeType(),
                'AWS設定' => [
                    'bucket' => env('AWS_BUCKET'),
                    'region' => env('AWS_DEFAULT_REGION'),
                    'disk' => config('filesystems.disks.s3')
                ]
            ]);

            try {
                // S3アップロードを試行
                $path = Storage::disk('s3')->putFileAs('', $file, $fileName, 'public');

                Log::info('S3アップロード結果', [
                    'path' => $path,
                    'fileName' => $fileName
                ]);

                if (!$path) {
                    throw new \Exception('パスの取得に失敗しました');
                }
            } catch (\Exception $s3Error) {
                Log::error('S3アップロード例外発生', [
                    'error' => $s3Error->getMessage(),
                    'fileName' => $fileName,
                    'trace' => $s3Error->getTraceAsString()
                ]);
                throw $s3Error;
            }

            // データベースにアイテムを保存
            $item = Item::create([
                'user_id' => $request->user_id,
                'name' => $request->name,
                'file_path' => $path,
                'thumbnail' => $path // サムネイルのパスも設定
            ]);

            return response()->json([
                'message' => 'アップロード成功',
                'item' => $item
            ], 201);
        } catch (\Exception $e) {
            Log::error('ファイルアップロードエラー詳細', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'アップロード失敗',
                'error' => $e->getMessage(),
                'details' => env('APP_DEBUG') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
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
}
