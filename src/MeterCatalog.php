<?php

declare(strict_types=1);

namespace Arud;

/**
 * Position-aware grammar of the Arabic meters.
 *
 * Each form lists its feet and, per position, the licensed variants:
 *   hashw – every foot except the last of a hemistich (the sound foot is implied)
 *   arud  – last foot of the first hemistich (bayt-shaped forms)
 *   darb  – last foot of the second hemistich / of a single line
 *
 * shape: 'bayt' (two hemistichs, ʿarūḍ + ḍarb) or 'line' (one self-contained line).
 * tier : 'classical' (standard forms) or 'rare' (attested but uncommon; the grammar
 *        is deliberately permissive and needs expert review – see docs/GRAMMAR.md).
 * labels.darb maps a ḍarb variant to the traditional sub-meter name
 *        (e.g. kamil majzuʾ + hadhadh = «أحذ الكامل»).
 *
 * `prior` is a popularity rank used ONLY as a last tie-breaker; see
 * THIRD_PARTY_NOTICES.md for its source.
 */
final class MeterCatalog
{
    private const Z_MU = ['khabn', 'tay', 'khabal'];
    private const Z_FL = ['khabn', 'kaff', 'shakl'];
    private const Z_MT = ['idmar', 'waqs', 'khazl'];
    private const Z_MF = ['aql', 'asb', 'naqs'];

    /** @var array<string,array<string,mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $meters = [
            'tawil' => self::meter('الطويل', 'Tawil', 1, [
                self::form('tam', ['faulun', 'mafailun', 'faulun', 'mafailun'],
                    [['qabd'], ['qabd', 'kaff'], ['qabd']],
                    ['qabd'], ['qabd', 'hadhf']),
            ]),
            'madid' => self::meter('المديد', 'Madid', 13, [
                self::form('tam', ['failatun', 'failun', 'failatun'],
                    [self::Z_FL, ['khabn']],
                    ['hadhf', 'hadhf_khabn', 'khabn', 'kaff', 'shakl'],
                    ['hadhf', 'hadhf_khabn', 'batr', 'khabn', 'kaff', 'qasr']),
                self::form('majzu', ['failatun', 'failun'],
                    [self::Z_FL],
                    ['khabn'], ['khabn', 'qat', 'khabn_qat'], tier: 'rare'),
            ]),
            'basit' => self::meter('البسيط', 'Basit', 3, [
                self::form('tam', ['mustafilun', 'failun', 'mustafilun', 'failun'],
                    [self::Z_MU, ['khabn'], self::Z_MU],
                    ['khabn', 'qat'], ['khabn', 'qat', 'tadhyil']),
                self::form('majzu', ['mustafilun', 'failun', 'mustafilun'],
                    [self::Z_MU, ['khabn']],
                    ['qat'], ['qat', 'tadhyil', 'khabn'], tier: 'rare'),
                self::form('mukhalla', ['mustafilun', 'failun', 'faulun'],
                    [self::Z_MU, ['khabn']],
                    ['qabd'], ['qabd'], tier: 'rare', label: 'مخلع البسيط'),
                self::form('manhuk', ['mustafilun', 'failun'],
                    [self::Z_MU],
                    [], ['khabn', 'qat'], shape: 'line', tier: 'rare'),
            ]),
            'wafir' => self::meter('الوافر', 'Wafir', 4, [
                self::form('tam', ['mafaalatun', 'mafaalatun', 'faulun'],
                    [self::Z_MF, self::Z_MF],
                    [], ['qabd']),
                self::form('majzu', ['mafaalatun', 'mafaalatun'],
                    [self::Z_MF],
                    self::Z_MF, ['aql', 'asb', 'naqs', 'qataf']),
            ]),
            'kamil' => self::meter('الكامل', 'Kamil', 2, [
                self::form('tam', ['mutafailun', 'mutafailun', 'mutafailun'],
                    [self::Z_MT, self::Z_MT],
                    ['idmar', 'hadhadh'],
                    ['idmar', 'qat', 'idmar_qat', 'hadhadh', 'tadhyil', 'tarfil'],
                    labels: ['qat' => 'الكامل المقطوع', 'idmar_qat' => 'الكامل المقطوع']),
                self::form('majzu', ['mutafailun', 'mutafailun'],
                    [self::Z_MT],
                    ['idmar'],
                    ['idmar', 'qat', 'idmar_qat', 'hadhadh', 'tadhyil', 'tarfil'],
                    labels: ['hadhadh' => 'أحذ الكامل']),
                self::form('manhuk', ['mutafailun', 'mutafailun'],
                    [self::Z_MT],
                    [], ['idmar', 'qat', 'idmar_qat', 'hadhadh'], shape: 'line', tier: 'rare'),
            ]),
            'hazaj' => self::meter('الهزج', 'Hazaj', 12, [
                self::form('majzu', ['mafailun', 'mafailun'],
                    [['qabd', 'kaff', 'kharm', 'kharb']],
                    ['qabd', 'kaff'], ['qabd', 'kaff', 'hadhf', 'qasr']),
            ]),
            'rajaz' => self::meter('الرجز', 'Rajaz', 8, [
                self::form('tam', ['mustafilun', 'mustafilun', 'mustafilun'],
                    [self::Z_MU, self::Z_MU],
                    [...self::Z_MU, 'qat'], [...self::Z_MU, 'qat', 'khabn_qat', 'tadhyil']),
                self::form('majzu', ['mustafilun', 'mustafilun'],
                    [self::Z_MU],
                    self::Z_MU, [...self::Z_MU, 'qat', 'khabn_qat', 'tadhyil']),
                self::form('mashtur', ['mustafilun', 'mustafilun', 'mustafilun'],
                    [self::Z_MU, self::Z_MU],
                    [], [...self::Z_MU, 'qat', 'khabn_qat', 'tadhyil'], shape: 'line', tier: 'rare'),
                self::form('manhuk', ['mustafilun', 'mustafilun'],
                    [self::Z_MU],
                    [], ['qat', 'khabn_qat'], shape: 'line', tier: 'rare'),
            ]),
            'ramal' => self::meter('الرمل', 'Ramal', 10, [
                self::form('tam', ['failatun', 'failatun', 'failatun'],
                    [self::Z_FL, self::Z_FL],
                    ['hadhf', 'hadhf_khabn', 'qasr', ...self::Z_FL],
                    ['hadhf', 'hadhf_khabn', 'qasr', 'batr', 'tasbigh', ...self::Z_FL]),
                self::form('majzu', ['failatun', 'failatun'],
                    [self::Z_FL],
                    self::Z_FL, ['hadhf', 'qasr', 'tasbigh', ...self::Z_FL]),
            ]),
            'saree' => self::meter('السريع', 'Saree', 6, [
                self::form('tam', ['mustafilun', 'mustafilun', 'mafoolatu'],
                    [self::Z_MU, self::Z_MU],
                    ['tay_kasf', 'kasf'], ['tay_kasf', 'kasf', 'waqf', 'salm']),
                self::form('mashtur', ['mustafilun', 'mustafilun', 'mafoolatu'],
                    [self::Z_MU, self::Z_MU],
                    [], ['tay_kasf', 'kasf', 'waqf', 'salm'], shape: 'line', tier: 'rare'),
            ]),
            'munsarih' => self::meter('المنسرح', 'Munsarih', 9, [
                self::form('tam', ['mustafilun', 'mafoolatu', 'mustafilun'],
                    [self::Z_MU, ['khabn', 'tay', 'khabal']],
                    ['tay', 'qat'], ['tay', 'qat']),
            ]),
            'khafif' => self::meter('الخفيف', 'Khafif', 5, [
                self::form('tam', ['failatun', 'mustafilun', 'failatun'],
                    [self::Z_FL, ['khabn', 'tay']],
                    ['hadhf', 'khabn', 'hadhf_khabn', 'shakl', 'kaff'],
                    ['hadhf', 'qasr', 'khabn', 'hadhf_khabn', 'shakl', 'kaff']),
                self::form('majzu', ['failatun', 'mustafilun'],
                    [self::Z_FL],
                    self::Z_MU, [...self::Z_MU, 'qat', 'khabn_qat']),
            ]),
            'mudari' => self::meter('المضارع', 'Mudari', 16, [
                self::form('majzu', ['mafailun', 'failatun'],
                    [['qabd', 'kaff', 'kharm', 'kharb']],
                    ['kaff'], ['kaff']),
            ]),
            'muqtadib' => self::meter('المقتضب', 'Muqtadib', 15, [
                self::form('majzu', ['mafoolatu', 'mustafilun'],
                    [['khabn', 'tay']],
                    ['tay'], ['tay', 'qat']),
            ]),
            'mujtath' => self::meter('المجتث', 'Mujtath', 11, [
                self::form('majzu', ['mustafilun', 'failatun'],
                    [['khabn']],
                    self::Z_FL, [...self::Z_FL, 'qasr']),
            ]),
            'mutaqarib' => self::meter('المتقارب', 'Mutaqarib', 7, [
                self::form('tam', ['faulun', 'faulun', 'faulun', 'faulun'],
                    [['qabd', 'thalm', 'tharm'], ['qabd'], ['qabd']],
                    ['hadhf'], ['hadhf', 'qasr', 'batr']),
                self::form('majzu', ['faulun', 'faulun', 'faulun'],
                    [['qabd', 'thalm', 'tharm'], ['qabd']],
                    ['hadhf'], ['hadhf', 'qasr', 'batr']),
            ]),
            'mutadarik' => self::meter('المتدارك', 'Mutadarik', 14, [
                self::form('tam', ['failun', 'failun', 'failun', 'failun'],
                    [['khabn', 'qat'], ['khabn', 'qat'], ['khabn', 'qat']],
                    ['khabn', 'qat', 'tadhyil'], ['khabn', 'qat', 'tadhyil', 'khabn_qat']),
                self::form('majzu', ['failun', 'failun', 'failun'],
                    [['khabn', 'qat'], ['khabn', 'qat']],
                    ['khabn', 'qat', 'tadhyil'], ['khabn', 'qat', 'tadhyil', 'khabn_qat']),
            ]),
        ];

        $meters = self::finalize($meters);

        return self::$cache = $meters;
    }

    /** @return array<string,mixed> */
    public static function get(string $key): array
    {
        return self::all()[$key] ?? throw new \InvalidArgumentException("Unknown meter '{$key}'.");
    }

    /**
     * Names listed by the poetry encyclopedia that this package does NOT model
     * as separate grammars, with what they map to (alias) or why they are missing.
     *
     * status: 'alias' (covered by form_id; optional darb label) | 'not_implemented'
     *
     * @return list<array<string,mixed>>
     */
    public static function extended(): array
    {
        $alias = static fn (string $ar, string $form, string $note = ''): array
            => ['name_ar' => $ar, 'status' => 'alias', 'form_id' => $form, 'note' => $note];
        $todo = static fn (string $ar, string $reason): array
            => ['name_ar' => $ar, 'status' => 'not_implemented', 'form_id' => null, 'note' => $reason];

        return [
            $alias('مجزوء الكامل', 'kamil:majzu'),
            $alias('أحذ الكامل', 'kamil:majzu', 'ضرب أحذ (حذذ)'),
            $alias('الكامل المقطوع', 'kamil:tam', 'ضرب مقطوع'),
            $alias('منهوك الكامل', 'kamil:manhuk'),
            $alias('مجزوء الرجز', 'rajaz:majzu'),
            $alias('مشطور الرجز', 'rajaz:mashtur'),
            $alias('منهوك الرجز', 'rajaz:manhuk'),
            $alias('مربع الرجز', 'rajaz:majzu', 'أربع تفعيلات في البيت'),
            $alias('مجزوء الرمل', 'ramal:majzu'),
            $alias('مجزوء الوافر', 'wafir:majzu'),
            $alias('مجزوء الخفيف', 'khafif:majzu'),
            $alias('مجزوء الهزج', 'hazaj:majzu'),
            $alias('الهزج', 'hazaj:majzu', 'لا يُستعمل تامًّا'),
            $alias('مجزوء المجتث', 'mujtath:majzu'),
            $alias('المجتث', 'mujtath:majzu', 'لا يُستعمل تامًّا'),
            $alias('مجزوء المتقارب', 'mutaqarib:majzu'),
            $alias('مجزوء المتدارك', 'mutadarik:majzu'),
            $alias('المضارع', 'mudari:majzu', 'لا يُستعمل تامًّا'),
            $alias('المقتضب', 'muqtadib:majzu', 'لا يُستعمل تامًّا'),
            $alias('مجزوء المقتضب', 'muqtadib:majzu'),
            $alias('مجزوء المديد', 'madid:majzu'),
            $alias('مجزوء البسيط', 'basit:majzu'),
            $alias('مخلع البسيط', 'basit:mukhalla'),
            $alias('منهوك البسيط', 'basit:manhuk'),
            $alias('مشطور السريع', 'saree:mashtur'),
            $alias('الخبب', 'mutadarik:tam', 'المتدارك بخبن التفعيلات'),
            $alias('المتدارك المنهوك', 'mutadarik:majzu', 'تقريب؛ يحتاج مراجعة'),
            $todo('مجزوء السريع', 'تفصيل العروض والضرب غير موثَّق بما يكفي'),
            $todo('مجزوء المنسرح', 'قليل جدًا؛ قواعده غير موثَّقة بما يكفي'),
            $todo('منهوك المنسرح', 'قليل جدًا؛ قواعده غير موثَّقة بما يكفي'),
            $todo('مجزوء الطويل', 'نادر؛ لم تُوثَّق قواعده'),
            $todo('مشطور الطويل', 'نادر؛ لم تُوثَّق قواعده'),
            $todo('أحذ الوافر', 'يتطلب تحديد الضرب الأحذ بمرجع'),
            $todo('أحذ المديد', 'يتطلب تحديد الضرب الأحذ بمرجع'),
            $todo('مخلع الرمل', 'قواعده غير موثَّقة بما يكفي'),
            $todo('مخلع الكامل', 'قواعده غير موثَّقة بما يكفي'),
            $todo('مخلع الرجز', 'قواعده غير موثَّقة بما يكفي'),
            $todo('مخلع السريع', 'قواعده غير موثَّقة بما يكفي'),
            $todo('مخلع موشح', 'نظم موشح: ليس وزنًا خليليًّا'),
            $todo('موشح', 'نظم موشح: ليس وزنًا خليليًّا'),
            $todo('مجزوء موشح', 'نظم موشح: ليس وزنًا خليليًّا'),
            $todo('الدوبيت', 'وزن فارسيّ الأصل خارج العروض الخليلي'),
            $todo('مجزوء الدوبيت', 'وزن فارسيّ الأصل خارج العروض الخليلي'),
            $todo('المواليا', 'فن شعبي خارج العروض الخليلي'),
            $todo('مجزوء المواليا', 'فن شعبي خارج العروض الخليلي'),
            $todo('القوما', 'فن شعبي خارج العروض الخليلي'),
            $todo('السلسلة', 'وزن مولَّد؛ لم تُوثَّق قواعده'),
            $todo('الممتد', 'وزن مولَّد؛ لم تُوثَّق قواعده'),
            $todo('منهوك الكامل', 'انظر kamil:manhuk'),
            $alias('بحر التفعيلة', 'free_verse', 'استعمل Analyzer::analyzeFreeVerse()'),
            $alias('تفعيلة الكامل', 'free_verse', 'استعمل Analyzer::analyzeFreeVerse()'),
            $alias('تفعيلة المتقارب', 'free_verse', 'استعمل Analyzer::analyzeFreeVerse()'),
            $alias('تفعيلة الرمل', 'free_verse', 'استعمل Analyzer::analyzeFreeVerse()'),
        ];
    }

    /**
     * Resolve an encyclopedia meter name (e.g. «مجزوء الكامل») to its status/form.
     *
     * @return array<string,mixed>|null
     */
    public static function resolveName(string $name): ?array
    {
        $needle = trim(preg_replace('/^بحر\s+/u', '', trim($name)) ?? $name);
        foreach (self::all() as $key => $meter) {
            foreach ($meter['forms'] as $form) {
                if ($needle === $form['display_ar'] || $needle === $meter['name_ar'] && $form['type'] === 'tam') {
                    return ['name_ar' => $needle, 'status' => 'implemented', 'form_id' => $form['id'], 'note' => ''];
                }
            }
        }
        foreach (self::extended() as $row) {
            if ($row['name_ar'] === $needle) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $forms
     * @return array<string,mixed>
     */
    private static function meter(string $ar, string $en, int $prior, array $forms): array
    {
        return ['name_ar' => $ar, 'name_en' => $en, 'prior' => $prior, 'forms' => $forms];
    }

    /**
     * @param list<string> $feet
     * @param list<list<string>> $hashw
     * @param list<string> $arud
     * @param list<string> $darb
     * @param array<string,string> $labels darb variant => sub-meter label
     * @return array<string,mixed>
     */
    private static function form(
        string $type,
        array $feet,
        array $hashw,
        array $arud,
        array $darb,
        string $shape = 'bayt',
        string $tier = 'classical',
        string $label = '',
        array $labels = [],
    ): array {
        return [
            'type' => $type,
            'shape' => $shape,
            'tier' => $tier,
            'feet' => $feet,
            'hashw' => $hashw,
            'arud' => $arud,
            'darb' => $darb,
            'label' => $label,
            'labels' => $labels,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $meters
     * @return array<string,array<string,mixed>>
     */
    private static function finalize(array $meters): array
    {
        $prefix = ['majzu' => 'مجزوء ', 'mashtur' => 'مشطور ', 'manhuk' => 'منهوك '];
        foreach ($meters as $key => &$meter) {
            foreach ($meter['forms'] as &$form) {
                $form['id'] = $key . ':' . $form['type'];
                $form['meter'] = $key;
                $form['display_ar'] = $form['label'] !== ''
                    ? $form['label']
                    : (($prefix[$form['type']] ?? '') . $meter['name_ar']);
                $count = count($form['feet']);
                if (count($form['hashw']) !== $count - 1) {
                    throw new \LogicException("Form {$form['id']}: hashw must list {$count}-1 positions.");
                }
                foreach ($form['feet'] as $i => $foot) {
                    $keys = $i === $count - 1
                        ? ($form['shape'] === 'line' ? $form['darb'] : array_unique([...$form['arud'], ...$form['darb']]))
                        : $form['hashw'][$i];
                    FootCatalog::options($foot, $keys); // validates every referenced variant
                }
            }
            unset($form);
        }
        unset($meter);

        return $meters;
    }
}
