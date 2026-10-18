<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Arud\Analyzer;

$analyzer = new Analyzer();

// 1) One hemistich
$shatr = $analyzer->analyze('قِفَا نَبْكِ مِنْ ذِكْرَى حَبِيبٍ وَمَنْزِلِ', side: 'sadr');
echo $shatr->pattern, ' → ', $shatr->bestMatch()['label_ar'], PHP_EOL;

// 2) One verse: two arguments, a list, or an assoc array
$verse = $analyzer->analyzeVerse(['قِفَا نَبْكِ مِنْ ذِكْرَى حَبِيبٍ وَمَنْزِلِ', 'بِسِقْطِ اللِّوَى بَيْنَ الدَّخُولِ فَحَوْمَلِ']);
echo $verse->confidence, ' ', $verse->bestMatch()['label_ar'], PHP_EOL;
foreach ($verse->bestMatch()['feet']['sadr'] as $foot) {
    echo '  ', $foot['name_ar'], ' [', $foot['role'], '] ', $foot['rule_ar'], PHP_EOL;
}

// 3) A whole poem: array of pairs, flat list, or text ("sadr *** ajuz" per line)
$poem = $analyzer->analyzePoem("إِنَّ الْعُيُونَ الَّتِي فِي طَرْفِهَا حَوَرٌ *** قَتَلْنَنَا ثُمَّ لَمْ يُحْيِينَ قَتْلَانَا\n"
    . 'السَّيْفُ أَصْدَقُ إِنْبَاءً مِنَ الْكُتُبِ *** فِي حَدِّهِ الْحَدُّ بَيْنَ الْجِدِّ وَاللَّعِبِ');
echo $poem->dominantMeter, ' ', $poem->confidence, PHP_EOL;

// 4) Let the library detect the shape
$any = $analyzer->analyzeAuto('قَدْ جَاءَ الرَّبِيعُ');
echo get_class($any), PHP_EOL;

// 5) JSON
echo json_encode($verse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
