<?php

namespace App\Http\Controllers;

use App\Services\ReadingReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * マイ読書レポートの画面を扱うコントローラー。
 */
class ReportController extends Controller
{
    /**
     * ログインユーザーのマイ読書レポートを表示する。
     *
     * 集計は ReadingReportService に任せ、結果を $stats として渡す。
     *
     * @param  Request  $request  リクエスト（ログインユーザーの取得に使う）
     * @param  ReadingReportService  $readingReportService  読書レポートの集計サービス
     * @return View マイ読書レポート画面（reports.index）
     */
    public function index(Request $request, ReadingReportService $readingReportService): View
    {
        return view('reports.index', [
            'stats' => $readingReportService->buildStats($request->user()),
        ]);
    }
}
