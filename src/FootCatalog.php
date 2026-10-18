<?php

declare(strict_types=1);

namespace Arud;

/**
 * Prosodic feet and their licensed modifications (zihāf / 'ilal).
 *
 * Variants are DERIVED from the base pattern by explicit, auditable operations
 * (1-based positions on the base pattern; '1' = mutaharrik, '0' = sākin):
 *   sakin  – set the letter at these positions to sākin
 *   del    – delete the letters at these positions
 *   append – append bits (tadhyīl / tasbīgh / tarfīl)
 * Operations are applied in that order. Nothing is hand-typed, so a variant can
 * never silently disagree with its rule (see tests/FootCatalogTest.php).
 */
final class FootCatalog
{
    /** @var array<string,array{0:string,1:string}> name_ar, pattern */
    private const FEET = [
        'faulun' => ['فعولن', '11010'],
        'mafailun' => ['مفاعيلن', '1101010'],
        'failun' => ['فاعلن', '10110'],
        'mustafilun' => ['مستفعلن', '1010110'],
        'mutafailun' => ['متفاعلن', '1110110'],
        'mafaalatun' => ['مفاعلتن', '1101110'],
        'mafoolatu' => ['مفعولاتُ', '1010101'],
        'failatun' => ['فاعلاتن', '1011010'],
    ];

    /**
     * variant => [rule_ar, rule_en, kind, realized_ar, del, sakin, append]
     *
     * @var array<string,array<string,array{0:string,1:string,2:string,3:string,4:list<int>,5:list<int>,6:string}>>
     */
    private const VARIANTS = [
        'faulun' => [
            'qabd' => ['القبض', 'Qabd', 'zihaf', 'فعولُ', [5], [], ''],
            'thalm' => ['الثلم', 'Thalm', 'zihaf', 'عولن', [1], [], ''],
            'tharm' => ['الثرم', 'Tharm', 'zihaf', 'عولُ', [1, 5], [], ''],
            'hadhf' => ['الحذف', 'Hadhf', 'illa', 'فعو', [4, 5], [], ''],
            'qasr' => ['القصر', 'Qasr', 'illa', 'فعولْ', [5], [4], ''],
            'batr' => ['البتر', 'Batr', 'illa', 'فَعْ', [3, 4, 5], [], ''],
        ],
        'mafailun' => [
            'qabd' => ['القبض', 'Qabd', 'zihaf', 'مفاعلن', [5], [], ''],
            'kaff' => ['الكف', 'Kaff', 'zihaf', 'مفاعيلُ', [7], [], ''],
            'shakl' => ['الشكل', 'Shakl', 'zihaf', 'مفاعلُ', [5, 7], [], ''],
            'kharm' => ['الخرم', 'Kharm', 'zihaf', 'فاعيلن', [1], [], ''],
            'kharb' => ['الخرب', 'Kharb', 'zihaf', 'فاعيلُ', [1, 7], [], ''],
            'hadhf' => ['الحذف', 'Hadhf', 'illa', 'فعولن', [6, 7], [], ''],
            'qasr' => ['القصر', 'Qasr', 'illa', 'مفاعيلْ', [7], [6], ''],
        ],
        'failun' => [
            'khabn' => ['الخبن', 'Khabn', 'zihaf', 'فعِلن', [2], [], ''],
            'qat' => ['القطع', 'Qat', 'illa', 'فعْلن', [5], [4], ''],
            'khabn_qat' => ['الخبن والقطع', 'Khabn + Qat', 'illa', 'فعْلْ', [2, 5], [4], ''],
            'tadhyil' => ['التذييل', 'Tadhyil', 'illa', 'فاعلان', [], [], '0'],
        ],
        'mustafilun' => [
            'khabn' => ['الخبن', 'Khabn', 'zihaf', 'مُتَفْعِلن', [2], [], ''],
            'tay' => ['الطي', 'Tay', 'zihaf', 'مفتعلن', [4], [], ''],
            'khabal' => ['الخبل', 'Khabal', 'zihaf', 'متعلن', [2, 4], [], ''],
            'qat' => ['القطع', 'Qat', 'illa', 'مفعولن', [7], [6], ''],
            'khabn_qat' => ['الخبن والقطع', 'Khabn + Qat', 'illa', 'فعولن', [2, 7], [6], ''],
            'tadhyil' => ['التذييل', 'Tadhyil', 'illa', 'مستفعلان', [], [], '0'],
        ],
        'mutafailun' => [
            'idmar' => ['الإضمار', 'Idmar', 'zihaf', 'مُتْفاعلن', [], [2], ''],
            'waqs' => ['الوقص', 'Waqs', 'zihaf', 'مفاعلن', [2], [], ''],
            'khazl' => ['الخزل', 'Khazl', 'zihaf', 'مُتْفَعِلن', [4], [2], ''],
            'qat' => ['القطع', 'Qat', 'illa', 'متفاعلْ', [7], [6], ''],
            'idmar_qat' => ['الإضمار والقطع', 'Idmar + Qat', 'illa', 'مفعولن', [7], [2, 6], ''],
            'hadhadh' => ['الحذذ', 'Hadhadh', 'illa', 'مُتَفا', [5, 6, 7], [], ''],
            'tadhyil' => ['التذييل', 'Tadhyil', 'illa', 'متفاعلان', [], [], '0'],
            'tarfil' => ['الترفيل', 'Tarfil', 'illa', 'متفاعلاتن', [], [], '10'],
        ],
        'mafaalatun' => [
            'asb' => ['العصب', 'Asb', 'zihaf', 'مفاعيلن', [], [5], ''],
            'aql' => ['العقل', 'Aql', 'zihaf', 'مفاعتن', [5], [], ''],
            'naqs' => ['النقص', 'Naqs', 'zihaf', 'مفاعيلُ', [7], [5], ''],
            'qataf' => ['القطف', 'Qataf', 'illa', 'فعولن', [6, 7], [5], ''],
        ],
        'mafoolatu' => [
            'khabn' => ['الخبن', 'Khabn', 'zihaf', 'مَعُولاتُ', [2], [], ''],
            'tay' => ['الطي', 'Tay', 'zihaf', 'مفعلاتُ', [4], [], ''],
            'khabal' => ['الخبل', 'Khabal', 'zihaf', 'معلاتُ', [2, 4], [], ''],
            'kasf' => ['الكسف', 'Kasf', 'illa', 'مفعولن', [7], [], ''],
            'waqf' => ['الوقف', 'Waqf', 'illa', 'مفعولاتْ', [], [7], ''],
            'tay_kasf' => ['الطي والكسف', 'Tay + Kasf', 'illa', 'فاعلن', [4, 7], [], ''],
            'salm' => ['الصلم', 'Salm', 'illa', 'فعلن', [5, 6, 7], [], ''],
        ],
        'failatun' => [
            'khabn' => ['الخبن', 'Khabn', 'zihaf', 'فعلاتن', [2], [], ''],
            'kaff' => ['الكف', 'Kaff', 'zihaf', 'فاعلاتُ', [7], [], ''],
            'shakl' => ['الشكل', 'Shakl', 'zihaf', 'فعلاتُ', [2, 7], [], ''],
            'hadhf' => ['الحذف', 'Hadhf', 'illa', 'فاعلن', [6, 7], [], ''],
            'hadhf_khabn' => ['الحذف والخبن', 'Hadhf + Khabn', 'illa', 'فعلن', [2, 6, 7], [], ''],
            'qasr' => ['القصر', 'Qasr', 'illa', 'فاعلاتْ', [7], [6], ''],
            'batr' => ['البتر', 'Batr', 'illa', 'فعْلن', [5, 6, 7], [4], ''],
            'tasbigh' => ['التسبيغ', 'Tasbigh', 'illa', 'فاعلاتان', [], [], '0'],
        ],
    ];

    /** @var array<string,array<string,mixed>>|null */
    private static ?array $cache = null;

    /**
     * @return array<string,array{key:string,name_ar:string,pattern:string,variants:array<string,array<string,mixed>>}>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $all = [];
        foreach (self::FEET as $key => [$name, $pattern]) {
            $variants = [];
            foreach (self::VARIANTS[$key] as $vKey => [$ruleAr, $ruleEn, $kind, $realized, $del, $sakin, $append]) {
                $variants[$vKey] = [
                    'key' => $vKey,
                    'pattern' => self::derive($pattern, $del, $sakin, $append),
                    'name_ar' => $realized,
                    'rule_ar' => $ruleAr,
                    'rule_en' => $ruleEn,
                    'kind' => $kind,
                    'ops' => ['del' => $del, 'sakin' => $sakin, 'append' => $append],
                ];
            }
            $all[$key] = ['key' => $key, 'name_ar' => $name, 'pattern' => $pattern, 'variants' => $variants];
        }

        return self::$cache = $all;
    }

    /** @return array{key:string,name_ar:string,pattern:string,variants:array<string,array<string,mixed>>} */
    public static function get(string $foot): array
    {
        return self::all()[$foot] ?? throw new \InvalidArgumentException("Unknown foot '{$foot}'.");
    }

    /**
     * Options for one foot slot: the sound foot ('s') plus the listed variants.
     *
     * @param list<string> $variantKeys 's' is always implied
     * @return list<array<string,mixed>>
     */
    public static function options(string $foot, array $variantKeys): array
    {
        $info = self::get($foot);
        $options = [[
            'key' => 's',
            'foot' => $foot,
            'pattern' => $info['pattern'],
            'name_ar' => $info['name_ar'],
            'rule_ar' => 'سالم',
            'rule_en' => 'Salim',
            'kind' => 'salim',
        ]];
        $seen = ['s' => true];
        foreach ($variantKeys as $vKey) {
            if (isset($seen[$vKey])) {
                continue;
            }
            $variant = $info['variants'][$vKey] ?? throw new \InvalidArgumentException("Unknown variant '{$vKey}' for foot '{$foot}'.");
            $seen[$vKey] = true;
            $options[] = [
                'key' => $vKey,
                'foot' => $foot,
                'pattern' => $variant['pattern'],
                'name_ar' => $variant['name_ar'],
                'rule_ar' => $variant['rule_ar'],
                'rule_en' => $variant['rule_en'],
                'kind' => $variant['kind'],
            ];
        }

        return $options;
    }

    /**
     * @param list<int> $del
     * @param list<int> $sakin
     */
    private static function derive(string $base, array $del, array $sakin, string $append): string
    {
        $bits = str_split($base);
        foreach ($sakin as $position) {
            $bits[$position - 1] = '0';
        }
        $out = '';
        foreach ($bits as $i => $bit) {
            if (!in_array($i + 1, $del, true)) {
                $out .= $bit;
            }
        }

        return $out . $append;
    }
}
