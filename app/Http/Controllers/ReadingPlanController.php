<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 読書計画を扱うコントローラー。
 *
 * 現時点では一覧（index）のみ実装している。
 * create / store / edit / update / destroy / complete はルートだけ定義済みで、これから実装する。
 */
class ReadingPlanController extends Controller
{
    /**
     * ログインユーザーの読書計画を、期日の近い順に一覧表示する。
     *
     * 並びは target_date の昇順、同じなら id の昇順。ページネーションはしない。
     * クエリの status が ReadingPlanStatus の値に一致する場合だけ、その状態で絞り込む
     * （一致しない値や未指定の場合は絞り込まない）。
     *
     * @param  Request  $request  リクエスト（ログインユーザーとクエリの status を使う）
     * @return View 読書計画一覧画面（reading-plans.index）。$currentStatus にはクエリの status をそのまま渡す
     */
    public function index(Request $request): View
    {
        $currentStatus = $request->query('status');
        $currentStatus = is_string($currentStatus) ? $currentStatus : null;
        $statusFilter = $currentStatus === null ? null : ReadingPlanStatus::tryFrom($currentStatus);

        $readingPlans = $request->user()
            ->readingPlans()
            ->with('book')
            ->when($statusFilter, fn (Builder $query, ReadingPlanStatus $status) => $query->where('status', $status->value))
            ->orderBy('target_date')
            ->orderBy('id')
            ->get();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }
}
