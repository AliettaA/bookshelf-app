<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * 全コントローラーの基底クラス。
 *
 * Policy による認可（authorize）とバリデーションの共通機能を提供する。
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
