<?php

declare(strict_types=1);

namespace Withdraw\Service;

defined('ABSPATH') || exit;

/**
 * The acknowledgement of a withdrawal declaration as a PDF, the customer's copy
 * on a durable medium (Art. 11a(4)).
 *
 * existing-checked: src/Email/AcknowledgementEmail.php - that class sends the acknowledgement as an email; a PDF is a separate file format with its own writer, which nothing in the plugin has. polski-pro uses TCPDF for the same document, about 3 MB with fonts, so this writes the few PDF objects it needs itself.
 *
 * One font, a subset of DejaVu Sans (Latin, Latin Extended, Greek, Cyrillic)
 * embedded as a CID font with Identity-H, so Polish, German and every other EU
 * language prints with its real letters. The font's own tables are read for
 * the character map and widths, so nothing is cached or written to disk.
 */
final class AcknowledgementPdf
{
    private const FONT   = 'assets/fonts/dejavu-sans-subset.ttf';
    private const WIDTH  = 595.28;
    private const HEIGHT = 841.89;
    private const MARGIN = 56.0;

    /** @var array<int, int> code point => glyph id */
    private array $cmap = [];

    /** @var array<int, int> glyph id => advance width in 1/1000 em */
    private array $widths = [];

    /** @var array<int, int> glyph id => code point, for the ToUnicode map */
    private array $used = [];

    /** @var array{ascent:int, descent:int, bbox:array<int,int>} */
    private array $metrics = ['ascent' => 0, 'descent' => 0, 'bbox' => [0, 0, 0, 0]];

    private string $font = '';

    /** @var list<string> */
    private array $pages = [];

    private string $page = '';

    private float $y = 0.0;

    /** The PDF bytes for one stored request, or '' if the font is missing. */
    public function render(object $request): string
    {
        if (! $this->loadFont()) {
            return '';
        }

        $this->pages = [];
        $this->used  = [];
        $this->newPage();

        $created = (string) ($request->created_at ?? '');
        $stamp   = $created !== '' ? (int) strtotime($created) : 0;
        $order   = wc_get_order((int) ($request->order_id ?? 0));
        $number  = $order instanceof \WC_Order ? (string) $order->get_order_number() : (string) ($request->order_hint ?? '');
        $when    = static fn (int $ts): string => $ts > 0
            ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $ts) . ' (' . wp_timezone_string() . ')'
            : '';

        $this->text(__('Acknowledgement of withdrawal', 'plogins-withdraw'), 16, 6);

        foreach ($this->shop() as $line) {
            $this->text($line, 10);
        }
        $this->gap(12);
        $this->rule();

        $rows = [
            __('Request', 'plogins-withdraw')       => '#' . (int) ($request->id ?? 0),
            __('Received', 'plogins-withdraw')      => $when($stamp),
            __('Order', 'plogins-withdraw')         => $number !== '' ? '#' . $number : '',
            __('Status', 'plogins-withdraw')        => RequestRepository::statusLabel((string) ($request->status ?? '')),
            __('Receipt code', 'plogins-withdraw')  => RequestRepository::receiptHash($request),
        ];
        foreach ($rows as $label => $value) {
            $this->text($label . ': ' . $value, 10, 2);
        }

        $items = json_decode((string) ($request->items ?? ''), true);
        if (is_array($items) && $items !== []) {
            $this->gap(8);
            $this->text(__('Items', 'plogins-withdraw'), 12, 4);
            foreach ($items as $item) {
                if (is_array($item)) {
                    $this->text('- ' . (string) ($item['name'] ?? '') . ' x ' . (int) ($item['qty'] ?? 0), 10, 2);
                }
            }
        }

        $declaration = trim((string) ($request->declaration ?? ''));
        if ($declaration !== '') {
            $this->gap(8);
            $this->text(__('Declaration as submitted', 'plogins-withdraw'), 12, 4);
            $this->text($declaration, 10, 2);
        }

        $this->gap(12);
        $this->rule();
        /* translators: %s: date and time the PDF was generated */
        $this->text(sprintf(__('Generated on %s. The status above is the status at that moment.', 'plogins-withdraw'), $when(current_time('timestamp'))), 8);

        $this->pages[] = $this->page;

        return $this->assemble(sprintf(
            /* translators: %d: withdrawal request id */
            __('Withdrawal request #%d', 'plogins-withdraw'),
            (int) ($request->id ?? 0),
        ));
    }

    /** @return list<string> Shop name and address, from the WooCommerce store settings. */
    private function shop(): array
    {
        $country = (string) get_option('woocommerce_default_country', '');
        $code    = explode(':', $country)[0];
        $names   = function_exists('WC') && WC()->countries instanceof \WC_Countries ? WC()->countries->get_countries() : [];

        return array_values(array_filter(array_map('trim', [
            wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
            (string) get_option('woocommerce_store_address', ''),
            (string) get_option('woocommerce_store_address_2', ''),
            trim(get_option('woocommerce_store_postcode', '') . ' ' . get_option('woocommerce_store_city', '')),
            (string) ($names[$code] ?? $code),
        ]), static fn (string $l): bool => $l !== ''));
    }

    private function newPage(): void
    {
        if ($this->page !== '') {
            $this->pages[] = $this->page;
        }
        $this->page = '';
        $this->y    = self::HEIGHT - self::MARGIN;
    }

    private function gap(float $points): void
    {
        $this->y -= $points;
    }

    private function rule(): void
    {
        $this->page .= sprintf("0.6 G 0.5 w %.2F %.2F m %.2F %.2F l S 0 G\n", self::MARGIN, $this->y, self::WIDTH - self::MARGIN, $this->y);
        $this->gap(12);
    }

    /** Write wrapped text at the cursor, starting a new page when it runs out. */
    private function text(string $text, float $size, float $after = 0.0): void
    {
        $max = self::WIDTH - 2 * self::MARGIN;

        foreach (preg_split('/\R/u', $text) ?: [] as $paragraph) {
            foreach ($this->wrap($paragraph, $size, $max) as $line) {
                if ($this->y - $size < self::MARGIN) {
                    $this->newPage();
                }
                $this->y    -= $size * 1.35;
                $this->page .= sprintf("BT /F1 %.1F Tf %.2F %.2F Td <%s> Tj ET\n", $size, self::MARGIN, $this->y, $this->encode($line));
            }
        }
        $this->y -= $after;
    }

    /**
     * Greedy line breaking by measured width; a word longer than the line (a
     * receipt code) is broken by character.
     *
     * @return list<string>
     */
    private function wrap(string $text, float $size, float $max): array
    {
        $lines = [];
        $line  = '';
        foreach (explode(' ', $text) as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($this->measure($try, $size) <= $max) {
                $line = $try;
                continue;
            }
            if ($line !== '') {
                $lines[] = $line;
            }
            $line = '';
            foreach ($this->chars($word) as $ch) {
                if ($line !== '' && $this->measure($line . $ch[1], $size) > $max) {
                    $lines[] = $line;
                    $line    = '';
                }
                $line .= $ch[1];
            }
        }
        $lines[] = $line;

        return $lines;
    }

    private function measure(string $text, float $size): float
    {
        $w = 0;
        foreach ($this->chars($text) as [$cp]) {
            $w += $this->widths[$this->cmap[$cp] ?? 0] ?? 0;
        }

        return $w * $size / 1000;
    }

    /** Glyph ids as hex, recording each for the ToUnicode map. */
    private function encode(string $text): string
    {
        $hex = '';
        foreach ($this->chars($text) as [$cp]) {
            $gid = $this->cmap[$cp] ?? 0;
            if ($gid > 0) {
                $this->used[$gid] = $cp;
            }
            $hex .= sprintf('%04X', $gid);
        }

        return $hex;
    }

    /**
     * UTF-8 decoded without mbstring, which WordPress does not require.
     *
     * @return list<array{0:int, 1:string}> code point and the character
     */
    private function chars(string $text): array
    {
        preg_match_all('/./us', $text, $m);
        $out = [];
        foreach ($m[0] as $ch) {
            $b = array_values((array) unpack('C*', $ch));
            $cp = match (count($b)) {
                1       => $b[0],
                2       => (($b[0] & 0x1F) << 6) | ($b[1] & 0x3F),
                3       => (($b[0] & 0x0F) << 12) | (($b[1] & 0x3F) << 6) | ($b[2] & 0x3F),
                default => (($b[0] & 0x07) << 18) | (($b[1] & 0x3F) << 12) | (($b[2] & 0x3F) << 6) | ($b[3] & 0x3F),
            };
            $out[] = [(int) $cp, $ch];
        }

        return $out;
    }

    /** Read the cmap (format 4), advance widths and the few metrics a PDF font descriptor needs. */
    private function loadFont(): bool
    {
        if ($this->font !== '') {
            return true;
        }
        $path = WITHDRAW_DIR . self::FONT;
        $data = is_readable($path) ? (string) file_get_contents($path) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file shipped with the plugin.
        if (strlen($data) < 12) {
            return false;
        }

        $u16    = static fn (int $o): int => (ord($data[$o]) << 8) | ord($data[$o + 1]);
        $s16    = static fn (int $o): int => ($v = (ord($data[$o]) << 8) | ord($data[$o + 1])) >= 0x8000 ? $v - 0x10000 : $v;
        $tables = [];
        for ($i = 0, $n = $u16(4); $i < $n; $i++) {
            $rec                           = 12 + 16 * $i;
            $tables[substr($data, $rec, 4)] = (int) unpack('N', substr($data, $rec + 8, 4))[1];
        }
        foreach (['head', 'hhea', 'hmtx', 'cmap'] as $tag) {
            if (! isset($tables[$tag])) {
                return false;
            }
        }

        $em    = $u16($tables['head'] + 18) ?: 1000;
        $scale = static fn (int $v): int => (int) round($v * 1000 / $em);

        $this->metrics = [
            'ascent'  => $scale($s16($tables['hhea'] + 4)),
            'descent' => $scale($s16($tables['hhea'] + 6)),
            'bbox'    => array_map(static fn (int $o): int => $scale($s16($tables['head'] + $o)), [36, 38, 40, 42]),
        ];

        $metricsCount = $u16($tables['hhea'] + 34);
        for ($g = 0; $g < $metricsCount; $g++) {
            $this->widths[$g] = $scale($u16($tables['hmtx'] + 4 * $g));
        }

        // Windows Unicode BMP subtable, format 4.
        $cmap = $tables['cmap'];
        $sub  = 0;
        for ($i = 0, $n = $u16($cmap + 2); $i < $n; $i++) {
            $rec = $cmap + 4 + 8 * $i;
            if ($u16($rec) === 3 && $u16($rec + 2) === 1) {
                $sub = $cmap + (int) unpack('N', substr($data, $rec + 4, 4))[1];
            }
        }
        if ($sub === 0 || $u16($sub) !== 4) {
            return false;
        }
        $segments = $u16($sub + 6) / 2;
        $ends     = $sub + 14;
        $starts   = $ends + 2 * $segments + 2;
        $deltas   = $starts + 2 * $segments;
        $ranges   = $deltas + 2 * $segments;
        for ($s = 0; $s < $segments; $s++) {
            $start = $u16($starts + 2 * $s);
            $end   = $u16($ends + 2 * $s);
            $delta = $u16($deltas + 2 * $s);
            $range = $u16($ranges + 2 * $s);
            for ($c = $start; $c <= $end && $c !== 0xFFFF; $c++) {
                if ($range === 0) {
                    $gid = ($c + $delta) & 0xFFFF;
                } else {
                    $gid = $u16($ranges + 2 * $s + $range + 2 * ($c - $start));
                    $gid = $gid === 0 ? 0 : ($gid + $delta) & 0xFFFF;
                }
                if ($gid > 0) {
                    $this->cmap[$c] = $gid;
                }
            }
        }
        $last = $this->widths[$metricsCount - 1] ?? 0;
        foreach ($this->cmap as $gid) {
            $this->widths[$gid] ??= $last;
        }

        $this->font = $data;

        return true;
    }

    /** Write the objects, the cross-reference table and the trailer. */
    private function assemble(string $title): string
    {
        $pageCount = count($this->pages);
        $objects   = [];
        $fontFile  = function_exists('gzcompress') ? (string) gzcompress($this->font, 9) : $this->font;
        $filter    = function_exists('gzcompress') ? ' /Filter /FlateDecode' : '';

        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (8 + 2 * $i) . ' 0 R';
        }

        ksort($this->used);
        $w = '';
        foreach ($this->used as $gid => $cp) {
            $w .= $gid . ' [' . ($this->widths[$gid] ?? 0) . '] ';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type0 /BaseFont /DejaVuSans /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 7 0 R >>';
        $objects[4] = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DejaVuSans /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 5 0 R /DW 600 /W [' . $w . '] /CIDToGIDMap /Identity >>';
        $objects[5] = sprintf(
            '<< /Type /FontDescriptor /FontName /DejaVuSans /Flags 32 /FontBBox [%s] /ItalicAngle 0 /Ascent %d /Descent %d /CapHeight %d /StemV 80 /FontFile2 6 0 R >>',
            implode(' ', $this->metrics['bbox']),
            $this->metrics['ascent'],
            $this->metrics['descent'],
            $this->metrics['ascent'],
        );
        $objects[6] = '<< /Length ' . strlen($fontFile) . ' /Length1 ' . strlen($this->font) . $filter . " >>\nstream\n" . $fontFile . "\nendstream";
        $objects[7] = $this->stream($this->toUnicode());

        foreach ($this->pages as $i => $content) {
            $objects[8 + 2 * $i] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>',
                self::WIDTH,
                self::HEIGHT,
                9 + 2 * $i,
            );
            $objects[9 + 2 * $i] = $this->stream($content);
        }
        $info           = 8 + 2 * $pageCount;
        $objects[$info] = '<< /Title <FEFF' . $this->utf16($title) . '> /Producer (Plogins Withdraw) /CreationDate (D:' . gmdate('YmdHis') . 'Z) >>';

        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf          .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . ($info + 1) . "\n0000000000 65535 f \n";
        for ($n = 1; $n <= $info; $n++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$n]);
        }

        return $pdf . 'trailer' . "\n<< /Size " . ($info + 1) . ' /Root 1 0 R /Info ' . $info . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    }

    private function stream(string $content): string
    {
        if (function_exists('gzcompress')) {
            $packed = (string) gzcompress($content, 9);

            return '<< /Length ' . strlen($packed) . " /Filter /FlateDecode >>\nstream\n" . $packed . "\nendstream";
        }

        return '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
    }

    /** Maps glyph ids back to text, so the PDF can be searched and copied from. */
    private function toUnicode(): string
    {
        $map = '';
        foreach (array_chunk($this->used, 100, true) as $chunk) {
            $map .= count($chunk) . " beginbfchar\n";
            foreach ($chunk as $gid => $cp) {
                $map .= sprintf("<%04X> <%s>\n", $gid, $this->codePointHex($cp));
            }
            $map .= "endbfchar\n";
        }

        return "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            . "/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
            . "1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n"
            . $map . "endcmap\nCMapName currentdict /CIDFont defineresource pop\nend\nend";
    }

    /** UTF-16BE as hex, for the document title. */
    private function utf16(string $text): string
    {
        return implode('', array_map(fn (array $c): string => $this->codePointHex($c[0]), $this->chars($text)));
    }

    private function codePointHex(int $cp): string
    {
        if ($cp > 0xFFFF) {
            $cp -= 0x10000;

            return sprintf('%04X%04X', 0xD800 | ($cp >> 10), 0xDC00 | ($cp & 0x3FF));
        }

        return sprintf('%04X', $cp);
    }
}
