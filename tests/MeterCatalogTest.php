<?php

declare(strict_types=1);

namespace Arud\Tests;

use Arud\Grammar;
use Arud\MeterCatalog;
use PHPUnit\Framework\TestCase;

final class MeterCatalogTest extends TestCase
{
    public function testSixteenClassicalMetersAreModelled(): void
    {
        $this->assertCount(16, MeterCatalog::all());
        foreach (MeterCatalog::all() as $key => $meter) {
            $classical = array_filter($meter['forms'], static fn (array $f): bool => $f['tier'] === 'classical');
            $this->assertNotEmpty($classical, "{$key} needs at least one classical form");
        }
    }

    public function testFormIdsAreUniqueAndWellFormed(): void
    {
        $ids = [];
        foreach (MeterCatalog::all() as $key => $meter) {
            foreach ($meter['forms'] as $form) {
                $this->assertSame($key . ':' . $form['type'], $form['id']);
                $this->assertNotContains($form['id'], $ids);
                $ids[] = $form['id'];
                $this->assertContains($form['shape'], ['bayt', 'line']);
                $this->assertContains($form['tier'], ['classical', 'rare']);
                $this->assertCount(count($form['feet']) - 1, $form['hashw']);
            }
        }
        $this->assertGreaterThanOrEqual(30, count($ids));
    }

    public function testEverySlotCompilesAndTemplateCountIsBounded(): void
    {
        foreach (MeterCatalog::all() as $meter) {
            foreach ($meter['forms'] as $form) {
                foreach (['sadr', 'ajuz', 'line'] as $role) {
                    $slots = Grammar::slots($form, $role);
                    $this->assertCount(count($form['feet']), $slots);
                    foreach ($slots as $options) {
                        $this->assertSame('s', $options[0]['key'], 'the sound foot is always first');
                    }
                    $templates = Grammar::templates($form['id'] . '#' . $role, $slots);
                    $this->assertLessThan(5000, count($templates), $form['id']);
                }
            }
        }
    }

    public function testPositionRulesAreEnforced(): void
    {
        $tawil = MeterCatalog::get('tawil')['forms'][0];
        $arud = array_column(Grammar::slots($tawil, 'sadr')[3], 'key');
        $this->assertContains('qabd', $arud);
        $this->assertNotContains('hadhf', $arud, 'Tawil ʿarūḍ is never maḥdhūfa');
        $first = array_column(Grammar::slots($tawil, 'sadr')[0], 'key');
        $this->assertNotContains('thalm', $first, 'thalm/tharm belong to Mutaqarib, not Tawil');
        $darb = array_column(Grammar::slots($tawil, 'ajuz')[3], 'key');
        $this->assertContains('hadhf', $darb);
    }

    public function testEncyclopediaNamesResolve(): void
    {
        $this->assertSame('kamil:majzu', MeterCatalog::resolveName('بحر مجزوء الكامل')['form_id']);
        $this->assertSame('implemented', MeterCatalog::resolveName('الطويل')['status']);
        $this->assertSame('tawil:tam', MeterCatalog::resolveName('بحر الطويل')['form_id']);
        $this->assertSame('not_implemented', MeterCatalog::resolveName('الدوبيت')['status']);
        $this->assertSame('alias', MeterCatalog::resolveName('أحذ الكامل')['status']);
        $this->assertNull(MeterCatalog::resolveName('بحر غير موجود'));
    }

    public function testEveryAliasPointsAtARealForm(): void
    {
        $ids = [];
        foreach (MeterCatalog::all() as $meter) {
            foreach ($meter['forms'] as $form) {
                $ids[] = $form['id'];
            }
        }
        foreach (MeterCatalog::extended() as $row) {
            if ($row['status'] === 'alias' && $row['form_id'] !== 'free_verse') {
                $this->assertContains($row['form_id'], $ids, $row['name_ar']);
            }
        }
    }
}
