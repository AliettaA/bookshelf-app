<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Google Books API との通信に失敗したことを表す例外。
 *
 * タイムアウトなどの接続エラー、エラー応答（4xx・5xx）、解釈できない応答のときに投げる。
 * 「該当する書籍がない」ことは失敗ではないため、この例外ではなく null で表す。
 */
class GoogleBooksException extends RuntimeException {}
