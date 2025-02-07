<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\UserLog;
use Illuminate\Support\Facades\Auth;

class LogUserActions
{
    public function handle(Request $request, Closure $next)
    {
        // リクエストの開始時にユーザーIDを取得（ログイン前のため0）
        $userId = Auth::id() ?? 0;
        $startTime = microtime(true);
        $response = $next($request);

        try {
            // レスポンス後に再度ユーザーIDを取得（ログイン後の場合は新しいID）
            $finalUserId = Auth::id() ?? $userId;

            // ログイン成功時の特別な処理
            if ($request->route()->getName() === 'login' && $response->status() === 204) {
                $finalUserId = Auth::id();
            }

            // 登録成功時の特別な処理
            if ($request->route()->getName() === 'register' && $response->status() === 204) {
                $finalUserId = Auth::id();
            }

            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000;

            // センシティブなパラメータを除外またはマスク
            $parameters = $request->except(['password', 'password_confirmation', 'current_password', 'token']);

            // メールアドレスをマスク
            if (isset($parameters['email'])) {
                $parameters['email'] = $this->maskEmail($parameters['email']);
            }

            // レスポンスの内容とステータスコードを取得
            $responseContent = null;
            $statusCode = null;

            // ルート情報の取得を改善
            $route = $request->route();
            $routeName = $route ? $route->getName() : 'unknown';

            // ステータスコードの取得方法を改善
            if ($response instanceof \Illuminate\Http\Response) {
                $statusCode = $response->status();
            } elseif ($response instanceof \Illuminate\Http\JsonResponse) {
                $statusCode = $response->status();
            }

            // エラー時のみレスポンス内容を記録
            if ($statusCode >= 400 && $response instanceof \Illuminate\Http\JsonResponse) {
                $responseData = json_decode($response->getContent(), true);
                if (is_array($responseData)) {
                    unset($responseData['password']);
                    unset($responseData['password_confirmation']);
                    unset($responseData['current_password']);
                    if (isset($responseData['email'])) {
                        $responseData['email'] = $this->maskEmail($responseData['email']);
                    }
                }
                $responseContent = json_encode($responseData);
            }

            UserLog::create([
                'user_id' => $finalUserId,
                'route_name' => $routeName,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'parameters' => json_encode($parameters),
                'status_code' => $statusCode ?? 204,
                'error_response' => $responseContent,
                'started_at' => now()->subMilliseconds($duration),
                'ended_at' => now(),
                'duration_ms' => $duration
            ]);

            if ($statusCode >= 400) {
                \Log::error('APIエラー', [
                    'user_id' => $finalUserId, // 更新されたユーザーIDを使用
                    'url' => $request->fullUrl(),
                    'status' => $statusCode,
                    'error_response' => $responseContent
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('ログ記録エラー', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return $response;
    }

    /**
     * メールアドレスをマスクする
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;

        $name = $parts[0];
        $domain = $parts[1];

        // ローカル部分をマスク
        $maskedName = substr($name, 0, 1) . str_repeat('*', strlen($name) - 1);

        return $maskedName . '@' . $domain;
    }
}
