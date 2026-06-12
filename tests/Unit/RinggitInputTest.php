<?php

use App\Support\RinggitInput;

test('toSen parses RM input strings with integer math', function (?string $input, ?int $expected) {
    expect(RinggitInput::toSen($input))->toBe($expected);
})->with([
    "'12.50' is 1250 sen" => ['12.50', 1250],
    "'12' is 1200 sen" => ['12', 1200],
    "'0.05' is 5 sen" => ['0.05', 5],
    'empty string is null' => ['', null],
    "'abc' is null" => ['abc', null],
    "'1,250.00' is 125000 sen" => ['1,250.00', 125000],
    'null is null' => [null, null],
    "'.50' is 50 sen" => ['.50', 50],
    "'RM 8.90' is 890 sen" => ['RM 8.90', 890],
    'lone dot is null' => ['.', null],
    'two dots is null' => ['1.2.3', null],
    'third decimal digit is truncated' => ['1.999', 199],
    "'0' is 0 sen" => ['0', 0],
]);

test('fromSen formats sen for form inputs', function (?int $sen, string $expected) {
    expect(RinggitInput::fromSen($sen))->toBe($expected);
})->with([
    '1250 sen is 12.50' => [1250, '12.50'],
    '5 sen is 0.05' => [5, '0.05'],
    '125000 sen is 1250.00' => [125000, '1250.00'],
    'null is empty' => [null, ''],
    '0 sen is 0.00' => [0, '0.00'],
]);

test('toSen and fromSen round-trip', function () {
    expect(RinggitInput::toSen(RinggitInput::fromSen(1250)))->toBe(1250)
        ->and(RinggitInput::fromSen(RinggitInput::toSen('12.50')))->toBe('12.50');
});
