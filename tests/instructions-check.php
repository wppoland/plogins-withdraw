<?php
/**
 * Generated Annex I texts per contract type (1.10.0): goods keeps the sales
 * wording, services and digital content count from the conclusion of the
 * contract and drop the goods paragraphs, only services get instruction 6.
 *
 * Run inside wp-env:  wp eval-file tests/instructions-check.php
 *
 * @package Withdraw/Tests
 */

use Withdraw\Migrator;
use Withdraw\Service\StatutoryText;

$saved = get_option(Migrator::OPTION_SETTINGS, []);
$fail  = 0;
$check = static function (string $label, bool $ok) use (&$fail): void {
    echo ($ok ? 'ok   ' : 'FAIL ') . $label . "\n";
    $fail += $ok ? 0 : 1;
};

$text = static function (string $sells) use ($saved): array {
    update_option(Migrator::OPTION_SETTINGS, array_merge(is_array($saved) ? $saved : [], ['sells' => $sells]));

    return [StatutoryText::instructions(), StatutoryText::modelForm()];
};

foreach (['goods' => [true, false, false], 'services' => [false, true, true], 'digital' => [false, true, false], 'bogus' => [true, false, false]] as $sells => [$isGoods, $fromContract, $item6]) {
    [$i, $f] = $text($sells);
    $check("$sells: physical possession", $isGoods === str_contains($i, 'physical possession of the goods'));
    $check("$sells: conclusion of the contract", $fromContract === str_contains($i, 'from the day of the conclusion of the contract'));
    $check("$sells: send back / withhold / diminished value", $isGoods === (str_contains($i, 'send back the goods') && str_contains($i, 'withhold reimbursement') && str_contains($i, 'diminished value')));
    $check("$sells: instruction 6", $item6 === str_contains($i, 'in proportion to what has been provided'));
    $check("$sells: form wording", ($sells === 'services') === str_contains($f, 'provision of the following service'));
}

update_option(Migrator::OPTION_SETTINGS, $saved);
echo $fail ? "instructions-check: $fail FAIL\n" : "instructions-check: OK\n";
