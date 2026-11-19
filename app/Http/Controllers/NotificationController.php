<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ログインユーザーの通知を扱うコントローラー。
 */
class NotificationController extends Controller
{
    /**
     * ログインユーザーの通知を新しい順にすべて表示する（ページネーションなし）。
     *
     * 新しい順は created_at の降順、同じなら id の降順。
     *
     * @param  Request  $request  リクエスト（ログインユーザーの取得に使う）
     * @return View 通知一覧画面（notifications.index）
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 通知を既読にして、通知一覧に戻る。
     *
     * ログインユーザー自身の通知の中から ID で探すため、他人の通知や存在しない ID は 404 になる。
     *
     * @param  Request  $request  リクエスト（ログインユーザーの取得に使う）
     * @param  string  $id  通知の ID（UUID）
     * @return RedirectResponse 通知一覧へのリダイレクト（success：通知を既読にしました。）
     */
    public function read(Request $request, string $id): RedirectResponse
    {
        $request->user()
            ->notifications()
            ->findOrFail($id)
            ->markAsRead();

        return redirect()
            ->route('notifications.index')
            ->with('success', '通知を既読にしました。');
    }
}
