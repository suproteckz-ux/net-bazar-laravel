<?php

namespace Tests\Unit;

use App\Console\Commands\GenerateUrlSlugs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for GenerateUrlSlugs::generateSlug() and ::transliterate().
 * No database, no framework boot — fast.
 */
class SlugGeneratorTest extends TestCase
{
    // ── generateSlug: real product names ─────────────────────────────────────

    #[DataProvider('productNameProvider')]
    public function test_generate_slug_from_product_name(string $name, string $expected): void
    {
        $this->assertSame($expected, GenerateUrlSlugs::generateSlug($name));
    }

    public static function productNameProvider(): array
    {
        return [
            // User's canonical example
            'LEX EDP 610 Wide Series' => [
                'LEX EDP 610 BL Wide Series духовой шкаф',
                'lex-edp-610-bl-wide-series-duhovoj-shkaf',
            ],

            // Vestfrost freezer — mixed Cyrillic/Latin
            'Vestfrost built-in freezer' => [
                'Встраиваемый морозильник VestFrost VFI F2791E БЕЛЫЙ',
                'vstraivaemyj-morozilnik-vestfrost-vfi-f2791e-belyj',
            ],

            // LEX microwave with dots in SKU
            'LEX BIMO microwave' => [
                'LEX BIMO 15.02N BBL микроволновая печь',
                'lex-bimo-15-02n-bbl-mikrovolnovaya-pech',
            ],

            // MEFERI exhaust — spaces in model name
            'MEFERI exhaust SMARTBOX' => [
                'Вытяжка полновстраиваемая SMARTBOX60IX POWER',
                'vytyazhka-polnovstraivaemaya-smartbox60ix-power',
            ],

            // Water heater — slash in name
            'water heater with slash' => [
                'Водонагреватель B/SLV800',
                'vodonagrevatel-b-slv800',
            ],

            // Office chair — long Cyrillic name with model number
            'office chair long name' => [
                'Офисное кресло с подушкой для поясницы Арт Строй Мебель модель 1174',
                'ofisnoe-kreslo-s-podushkoj-dlya-poyasnitsy-art-stroj-mebel-model-1174',
            ],

            // Cyrillic SKU in name
            'Maunfeld washing machine' => [
                'Стиральная машина с сушкой MAUNFELD MFWD12107ISTWH06',
                'stiralnaya-mashina-s-sushkoj-maunfeld-mfwd12107istwh06',
            ],

            // Already Latin SKU, Cyrillic category word only
            'Vestfrost IHV15' => [
                'Встраиваемая варочная поверхность VestFrost IHV 15',
                'vstraivaemaya-varochnaya-poverhnost-vestfrost-ihv-15',
            ],

            // Only Latin, no Cyrillic
            'pure latin' => [
                'Lavr LN2137 cleaner',
                'lavr-ln2137-cleaner',
            ],

            // Comma, parentheses, quotes stripped
            'special chars in name' => [
                'Офисное кресло с сеткой 1361A, доставка по Казахстану',
                'ofisnoe-kreslo-s-setkoj-1361a-dostavka-po-kazahstanu',
            ],
        ];
    }

    // ── generateSlug: edge cases ──────────────────────────────────────────────

    public function test_empty_name_returns_empty_string(): void
    {
        $this->assertSame('', GenerateUrlSlugs::generateSlug(''));
    }

    public function test_only_special_chars_returns_empty_string(): void
    {
        $this->assertSame('', GenerateUrlSlugs::generateSlug('(),,,,---...'));
    }

    public function test_no_leading_or_trailing_hyphens(): void
    {
        $slug = GenerateUrlSlugs::generateSlug('  Холодильник  ');
        $this->assertStringStartsNotWith('-', $slug);
        $this->assertStringEndsNotWith('-', $slug);
    }

    public function test_no_consecutive_hyphens(): void
    {
        $slug = GenerateUrlSlugs::generateSlug('A  B   C (D)');
        $this->assertStringNotContainsString('--', $slug);
    }

    public function test_max_length_truncated_at_word_boundary(): void
    {
        // 5 10-char Latin words → 54 chars without limit
        $name  = 'abcdefghij klmnopqrst uvwxyzabcd efghijklmn opqrstuvwx';
        $slug  = GenerateUrlSlugs::generateSlug($name, 30);
        $this->assertLessThanOrEqual(30, strlen($slug));
        $this->assertStringEndsNotWith('-', $slug);
    }

    public function test_result_is_lowercase(): void
    {
        $slug = GenerateUrlSlugs::generateSlug('SAMSUNG SBS2795E Холодильник');
        $this->assertSame(strtolower($slug), $slug);
    }

    public function test_result_contains_only_alphanumeric_and_hyphens(): void
    {
        $slug = GenerateUrlSlugs::generateSlug('Холодильник SAMSUNG (frost-free) — 2024!');
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $slug);
    }

    // ── transliterate: specific letter mappings ───────────────────────────────

    public function test_transliterate_kh_to_h(): void
    {
        // Х → h (not kh) — matches Satu slug convention
        $this->assertSame('holodilnik', GenerateUrlSlugs::transliterate('холодильник'));
        $this->assertSame('duhovoj', GenerateUrlSlugs::transliterate('духовой'));
    }

    public function test_transliterate_j_for_soft_j(): void
    {
        // Й → j
        $this->assertStringContainsString('j', GenerateUrlSlugs::transliterate('Холодильник встраиваемый'));
        $this->assertSame('j', GenerateUrlSlugs::transliterate('й'));
    }

    public function test_transliterate_soft_and_hard_signs_dropped(): void
    {
        $this->assertSame('', GenerateUrlSlugs::transliterate('ьъ'));
    }

    public function test_transliterate_all_vowels(): void
    {
        $result = GenerateUrlSlugs::transliterate('аеёиоуыэюя');
        $this->assertSame('aeyoiouyeyuya', $result);
    }

    public function test_transliterate_digraph_letters(): void
    {
        // Ж→zh, Ш→sh, Щ→shch, Ч→ch, Ц→ts
        $this->assertSame('zh', GenerateUrlSlugs::transliterate('ж'));
        $this->assertSame('sh', GenerateUrlSlugs::transliterate('ш'));
        $this->assertSame('shch', GenerateUrlSlugs::transliterate('щ'));
        $this->assertSame('ch', GenerateUrlSlugs::transliterate('ч'));
        $this->assertSame('ts', GenerateUrlSlugs::transliterate('ц'));
    }

    public function test_transliterate_uppercase_matches_lowercase(): void
    {
        $upper = GenerateUrlSlugs::transliterate('ДУХОВОЙ ШКАФ');
        $lower = GenerateUrlSlugs::transliterate('духовой шкаф');
        $this->assertSame(strtolower($upper), $lower);
    }
}
