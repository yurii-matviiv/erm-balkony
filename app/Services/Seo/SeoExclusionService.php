<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\DB;

/**
 * Виключені ключові слова: лише ховаються у вкладках SEO-позицій (дані в знімках лишаються).
 * Слова додаються вручну або автоматично за правилами config('seo.exclude_rules'); видалене вручну не повертається.
 */
class SeoExclusionService
{
    public static function norm(string $kw): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $kw)));
    }

    /** @return array<string,true> хеші активних виключень (кеш на запит) */
    public function hashes(): array
    {
        static $cache = null;

        return $cache ??= DB::table('seo_excluded_keywords')->whereNull('deleted_at')->pluck('keyword_hash')->flip()->map(fn () => true)->all();
    }

    public function isExcluded(string $kw): bool
    {
        return isset($this->hashes()[sha1(self::norm($kw))]);
    }

    public function count(): int
    {
        return count($this->hashes());
    }

    /** Чи підпадає слово під автоправила (дія + об’єкт). */
    public function matchesRules(string $kw): bool
    {
        $kw = self::norm($kw);
        foreach ((array) config('seo.exclude_rules') as $rule) {
            if (isset($rule['any'])) {
                if (collect($rule['any'])->contains(fn ($w) => mb_strpos($kw, $w) !== false)) {
                    return true;
                }

                continue;
            }
            $act = collect($rule['action'])->contains(fn ($w) => mb_strpos($kw, $w) !== false);
            $obj = collect($rule['object'])->contains(fn ($w) => mb_strpos($kw, $w) !== false);
            if ($act && $obj) {
                return true;
            }
        }

        return false;
    }

    /** @param iterable<string> $words @return int скільки доданих (вже наявні, зокрема видалені вручну, пропускаються) */
    public function add(iterable $words, string $source = 'manual'): int
    {
        $n = 0;
        foreach ($words as $w) {
            $w = self::norm((string) $w);
            if ($w === '') {
                continue;
            }
            $hash = sha1($w);
            $row = DB::table('seo_excluded_keywords')->where('keyword_hash', $hash)->first();
            if ($row) {
                // Ручне додавання повертає видалене слово; автододавання — ні.
                if ($source === 'manual' && $row->deleted_at) {
                    DB::table('seo_excluded_keywords')->where('id', $row->id)->update(['deleted_at' => null, 'source' => 'manual']);
                    $n++;
                }

                continue;
            }
            DB::table('seo_excluded_keywords')->insert(['keyword' => $w, 'keyword_hash' => $hash, 'source' => $source, 'created_at' => now()]);
            $n++;
        }

        return $n;
    }

    public function remove(int $id): void
    {
        DB::table('seo_excluded_keywords')->where('id', $id)->update(['deleted_at' => now()]);
    }

    /** Автододавання нових слів, що підпадають під правила (викликається після знімка). */
    public function autoAdd(iterable $queries): int
    {
        $hit = [];
        foreach ($queries as $q) {
            if ($q !== '' && $this->matchesRules($q)) {
                $hit[] = $q;
            }
        }

        return $this->add($hit, 'auto');
    }

    public function seedFromSnapshots(): int
    {
        $q = DB::table('seo_position_snapshots')->where('query', '!=', '')->distinct()->pluck('query');

        return $this->autoAdd($q);
    }
}
