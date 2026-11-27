<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 読書計画を扱うコントローラー。
 *
 * 操作後はすべて一覧（reading-plans.index）へ戻る。
 * 作成・更新・読了は1回の INSERT / UPDATE で完結するため、DB::transaction() は使っていない。
 * 削除は通知も一緒に削除するため、ReadingPlan::deleteWithNotifications() の中で使っている。
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

    /**
     * 読書計画の作成画面を表示する。
     *
     * @return View 作成画面（reading-plans.create）。$books には全書籍をタイトル順（同じなら id 順）で渡す
     */
    public function create(): View
    {
        $books = Book::orderBy('title')->orderBy('id')->get();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * ログインユーザーの読書計画を、状態を進行中にして作成する。
     *
     * @param  StoreReadingPlanRequest  $request  検証済みのリクエスト（book_id・target_date）
     * @return RedirectResponse 一覧へのリダイレクト（success：読書計画を作成しました。）
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $request->user()->readingPlans()->create([
            ...$request->validated(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を作成しました。');
    }

    /**
     * 読書計画の編集画面を表示する。
     *
     * @param  ReadingPlan  $readingPlan  編集する読書計画
     * @return View 編集画面（reading-plans.edit）
     *
     * @throws AuthorizationException 本人でない場合、または完了した計画の場合（403）
     */
    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->load('book');

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 読書計画の期日を更新する。期限切れの計画は、状態を進行中に戻す。
     *
     * @param  UpdateReadingPlanRequest  $request  検証済みのリクエスト（target_date）
     * @param  ReadingPlan  $readingPlan  更新する読書計画
     * @return RedirectResponse 一覧へのリダイレクト（success：読書計画を更新しました。）
     *
     * @throws AuthorizationException 本人でない場合、または完了した計画の場合（403）
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->reschedule($request->validated('target_date'));

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * 読書計画と、その計画の通知を削除する（状態に関わらず、本人なら削除できる）。
     *
     * @param  ReadingPlan  $readingPlan  削除する読書計画
     * @return RedirectResponse 一覧へのリダイレクト（success：読書計画を削除しました。）
     *
     * @throws AuthorizationException 本人でない場合（403）
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        $readingPlan->deleteWithNotifications();

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    /**
     * 読書計画を完了にし、操作した日時を completed_at に記録する。
     *
     * @param  ReadingPlan  $readingPlan  完了にする読書計画（進行中または期限切れ）
     * @return RedirectResponse 一覧へのリダイレクト（success：読書計画を完了しました。）
     *
     * @throws AuthorizationException 本人でない場合、または完了済みの計画の場合（403）
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('complete', $readingPlan);

        $readingPlan->markAsCompleted();

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を完了しました。');
    }
}
