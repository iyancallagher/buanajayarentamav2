<?php
/**
 * Helper bikin badge pill berwarna.
 * Cara pakai: echo renderBadge('Menipis', 'yellow');
 * Warna yang didukung: blue, green, yellow, red, slate
 */
function renderBadge(string $label, string $color = 'slate'): string
{
    $colorMap = [
        'blue'   => 'bg-blue-50 text-blue-600 border border-blue-100',
        'green'  => 'bg-green-50 text-green-600 border border-green-100',
        'yellow' => 'bg-yellow-50 text-yellow-600 border border-yellow-100', // Ini untuk warna orange/kritis
        'red'    => 'bg-red-50 text-red-600 border border-red-100',
        'slate'  => 'bg-slate-100 text-slate-600 border border-slate-200',
    ];

    $dotColorMap = [
        'blue'   => 'bg-blue-500',
        'green'  => 'bg-green-500',
        'yellow' => 'bg-yellow-500',
        'red'    => 'bg-red-500',
        'slate'  => 'bg-slate-400',
    ];

    $classes  = $colorMap[$color] ?? $colorMap['slate'];
    $dotClass = $dotColorMap[$color] ?? $dotColorMap['slate'];

    return '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold ' . $classes . '">'
         . '<span class="w-1.5 h-1.5 rounded-full ' . $dotClass . '"></span>'
         . htmlspecialchars($label)
         . '</span>';
}