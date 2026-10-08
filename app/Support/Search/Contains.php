<?php

namespace App\Support\Search;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Case-insensitive "contains" matching that treats the term literally.
 *
 * Wildcards in the term are escaped and the escape character is declared,
 * which keeps behaviour identical on PostgreSQL and SQLite (Laravel's
 * whereLike has no ESCAPE clause, and SQLite has no default escape).
 */
class Contains
{
    /**
     * Constrain the query to rows where any of the columns contains the term.
     *
     * Columns are written in code, never taken from input, so they are typed
     * as literal strings and can safely form part of the SQL.
     *
     * @template TBuilder of BuilderContract
     *
     * @param  TBuilder  $query
     * @param  list<literal-string>  $columns
     * @return TBuilder
     */
    public static function any(BuilderContract $query, array $columns, string $term): BuilderContract
    {
        $pattern = '%'.self::escape(mb_strtolower($term)).'%';

        $query->where(function (BuilderContract $query) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $query->orWhereRaw('lower('.$column.") like ? escape '\\'", [$pattern]);
            }
        });

        return $query;
    }

    public static function escape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
