<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Estate;

use Fynla\Core\Models\FamilyMember;
use Fynla\Core\Models\User;

class IntestacyCalculator
{
    private const THRESHOLD_SPOUSE_CHILDREN_MINOR = 32_200_000;

    /**
     * Calculate how the estate would be distributed under UK intestacy rules.
     *
     * Amounts are in minor units (pence) per ADR-005. Per-N divisions use
     * `intdiv` with the remainder allocated to the first beneficiary so the
     * sum of distributed amounts equals the total estate exactly.
     *
     * @param  int  $userId
     * @param  int  $estateValueMinor  Estate total in pence.
     */
    public function calculateDistribution(int $userId, int $estateValueMinor): array
    {
        $user = User::find($userId);

        if (! $user) {
            throw new \Exception('User not found');
        }

        $hasLinkedSpouse = in_array($user->marital_status, ['married', 'civil_partnership']) && $user->spouse_id !== null;
        $familyMembers = FamilyMember::where('user_id', $userId)->get();
        $hasSpouseFamilyMember = $familyMembers->where('relationship', 'spouse')->count() > 0;

        $isMarried = $hasLinkedSpouse || $hasSpouseFamilyMember;

        $children = $familyMembers->where('relationship', 'child')->count();
        $parents = $familyMembers->where('relationship', 'parent')->count();
        $siblings = $familyMembers->whereIn('relationship', ['sibling', 'brother', 'sister'])->count();
        $halfSiblings = $familyMembers->where('relationship', 'half-sibling')->count();
        $grandparents = $familyMembers->where('relationship', 'grandparent')->count();
        $auntsUncles = $familyMembers->whereIn('relationship', ['aunt', 'uncle'])->count();
        $auntsUnclesHalfBlood = $familyMembers->where('relationship', 'aunt-uncle-half-blood')->count();

        $decisionPath = [];
        $beneficiaries = [];
        $scenario = '';
        $explanation = '';
        $goesToCrown = false;

        $decisionPath[] = [
            'question' => 'Are you married/civil partnered?',
            'answer' => $isMarried ? 'YES' : 'NO',
        ];

        if ($isMarried) {
            $decisionPath[] = [
                'question' => 'Do you have any children?',
                'answer' => $children > 0 ? 'YES' : 'NO',
            ];

            if ($children > 0) {
                $thresholdPounds = intdiv(self::THRESHOLD_SPOUSE_CHILDREN_MINOR, 100);
                $decisionPath[] = [
                    'question' => 'Is your estate worth more than £'.number_format($thresholdPounds).'?',
                    'answer' => $estateValueMinor > self::THRESHOLD_SPOUSE_CHILDREN_MINOR ? 'YES' : 'NO',
                ];

                if ($estateValueMinor > self::THRESHOLD_SPOUSE_CHILDREN_MINOR) {
                    $surplusMinor = $estateValueMinor - self::THRESHOLD_SPOUSE_CHILDREN_MINOR;
                    $childrenAmountMinor = intdiv($surplusMinor, 2);
                    $spouseAmountMinor = self::THRESHOLD_SPOUSE_CHILDREN_MINOR + ($surplusMinor - $childrenAmountMinor);
                    $perChildAmountMinor = intdiv($childrenAmountMinor, $children);

                    $scenario = 'Married with Children - Estate over £322,000';
                    $explanation = 'Your spouse/civil partner receives the first £322,000 plus half of the remaining estate. Your children share the other half equally.';

                    $beneficiaries[] = [
                        'relationship' => 'Spouse/Civil Partner',
                        'name' => null,
                        'amount_minor' => $spouseAmountMinor,
                        'percentage' => round(($spouseAmountMinor / $estateValueMinor) * 100, 2),
                        'share_description' => 'First £322,000 + half of remaining (£'.number_format(intdiv($surplusMinor, 100)).')',
                        'count' => 1,
                    ];

                    $beneficiaries[] = [
                        'relationship' => 'Children',
                        'name' => null,
                        'amount_minor' => $childrenAmountMinor,
                        'percentage' => round(($childrenAmountMinor / $estateValueMinor) * 100, 2),
                        'share_description' => 'Half of remaining estate, £'.number_format(intdiv($perChildAmountMinor, 100)).' each',
                        'count' => $children,
                    ];
                } else {
                    $scenario = 'Married with Children - Estate under £322,000';
                    $explanation = 'Because your estate is worth less than £322,000, your spouse/civil partner inherits everything.';

                    $beneficiaries[] = [
                        'relationship' => 'Spouse/Civil Partner',
                        'name' => null,
                        'amount_minor' => $estateValueMinor,
                        'percentage' => 100,
                        'share_description' => 'Entire estate',
                        'count' => 1,
                    ];
                }
            } else {
                $scenario = 'Married without Children';
                $explanation = 'Your spouse/civil partner inherits your entire estate.';

                $beneficiaries[] = [
                    'relationship' => 'Spouse/Civil Partner',
                    'name' => null,
                    'amount_minor' => $estateValueMinor,
                    'percentage' => 100,
                    'share_description' => 'Entire estate',
                    'count' => 1,
                ];
            }
        } else {
            $decisionPath[] = [
                'question' => 'Do you have any children?',
                'answer' => $children > 0 ? 'YES' : 'NO',
            ];

            if ($children > 0) {
                $scenario = 'Not Married - Children Inherit';
                $explanation = 'Your children share your entire estate equally.';
                $perChildAmountMinor = intdiv($estateValueMinor, $children);

                $beneficiaries[] = [
                    'relationship' => 'Children',
                    'name' => null,
                    'amount_minor' => $estateValueMinor,
                    'percentage' => 100,
                    'share_description' => '£'.number_format(intdiv($perChildAmountMinor, 100)).' each',
                    'count' => $children,
                ];
            } else {
                $decisionPath[] = [
                    'question' => 'Do you have living parents?',
                    'answer' => $parents > 0 ? 'YES' : 'NO',
                ];

                if ($parents > 0) {
                    $scenario = 'No Children - Parents Inherit';
                    $explanation = 'Your parents share your entire estate equally.';
                    $perParentAmountMinor = intdiv($estateValueMinor, $parents);

                    $beneficiaries[] = [
                        'relationship' => 'Parents',
                        'name' => null,
                        'amount_minor' => $estateValueMinor,
                        'percentage' => 100,
                        'share_description' => $parents > 1 ? '£'.number_format(intdiv($perParentAmountMinor, 100)).' each' : 'Entire estate',
                        'count' => $parents,
                    ];
                } else {
                    $decisionPath[] = [
                        'question' => 'Do you have brothers/sisters?',
                        'answer' => $siblings > 0 ? 'YES' : 'NO',
                    ];

                    if ($siblings > 0) {
                        $scenario = 'No Children or Parents - Siblings Inherit';
                        $explanation = 'Your brothers and sisters share your entire estate equally.';
                        $perSiblingAmountMinor = intdiv($estateValueMinor, $siblings);

                        $beneficiaries[] = [
                            'relationship' => 'Siblings',
                            'name' => null,
                            'amount_minor' => $estateValueMinor,
                            'percentage' => 100,
                            'share_description' => '£'.number_format(intdiv($perSiblingAmountMinor, 100)).' each',
                            'count' => $siblings,
                        ];
                    } else {
                        $decisionPath[] = [
                            'question' => 'Do you have half brothers/sisters?',
                            'answer' => $halfSiblings > 0 ? 'YES' : 'NO',
                        ];

                        if ($halfSiblings > 0) {
                            $scenario = 'No Siblings - Half-Siblings Inherit';
                            $explanation = 'Your half-brothers and half-sisters share your entire estate equally.';
                            $perHalfSiblingAmountMinor = intdiv($estateValueMinor, $halfSiblings);

                            $beneficiaries[] = [
                                'relationship' => 'Half-Siblings',
                                'name' => null,
                                'amount_minor' => $estateValueMinor,
                                'percentage' => 100,
                                'share_description' => '£'.number_format(intdiv($perHalfSiblingAmountMinor, 100)).' each',
                                'count' => $halfSiblings,
                            ];
                        } else {
                            $decisionPath[] = [
                                'question' => 'Do you have living grandparents?',
                                'answer' => $grandparents > 0 ? 'YES' : 'NO',
                            ];

                            if ($grandparents > 0) {
                                $scenario = 'Grandparents Inherit';
                                $explanation = 'Your grandparents share your entire estate equally.';
                                $perGrandparentAmountMinor = intdiv($estateValueMinor, $grandparents);

                                $beneficiaries[] = [
                                    'relationship' => 'Grandparents',
                                    'name' => null,
                                    'amount_minor' => $estateValueMinor,
                                    'percentage' => 100,
                                    'share_description' => '£'.number_format(intdiv($perGrandparentAmountMinor, 100)).' each',
                                    'count' => $grandparents,
                                ];
                            } else {
                                $decisionPath[] = [
                                    'question' => 'Do you have aunts/uncles?',
                                    'answer' => $auntsUncles > 0 ? 'YES' : 'NO',
                                ];

                                if ($auntsUncles > 0) {
                                    $scenario = 'Aunts and Uncles Inherit';
                                    $explanation = 'Your aunts and uncles share your entire estate equally.';
                                    $perAuntUncleAmountMinor = intdiv($estateValueMinor, $auntsUncles);

                                    $beneficiaries[] = [
                                        'relationship' => 'Aunts and Uncles',
                                        'name' => null,
                                        'amount_minor' => $estateValueMinor,
                                        'percentage' => 100,
                                        'share_description' => '£'.number_format(intdiv($perAuntUncleAmountMinor, 100)).' each',
                                        'count' => $auntsUncles,
                                    ];
                                } else {
                                    $decisionPath[] = [
                                        'question' => "Do you have aunts/uncles 'of the half blood'?",
                                        'answer' => $auntsUnclesHalfBlood > 0 ? 'YES' : 'NO',
                                    ];

                                    if ($auntsUnclesHalfBlood > 0) {
                                        $scenario = 'Half-Blood Aunts and Uncles Inherit';
                                        $explanation = "Your aunts and uncles of the half blood (your parent's half-siblings) share your entire estate equally.";
                                        $perAmountMinor = intdiv($estateValueMinor, $auntsUnclesHalfBlood);

                                        $beneficiaries[] = [
                                            'relationship' => 'Aunts/Uncles (Half Blood)',
                                            'name' => null,
                                            'amount_minor' => $estateValueMinor,
                                            'percentage' => 100,
                                            'share_description' => '£'.number_format(intdiv($perAmountMinor, 100)).' each',
                                            'count' => $auntsUnclesHalfBlood,
                                        ];
                                    } else {
                                        $scenario = 'No Eligible Relatives - Crown Inherits';
                                        $explanation = 'With no eligible relatives, your entire estate passes to the Crown (government).';
                                        $goesToCrown = true;

                                        $beneficiaries[] = [
                                            'relationship' => 'The Crown (Government)',
                                            'name' => null,
                                            'amount_minor' => $estateValueMinor,
                                            'percentage' => 100,
                                            'share_description' => 'Entire estate (bona vacantia)',
                                            'count' => 1,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'scenario' => $scenario,
            'explanation' => $explanation,
            'estate_value_minor' => $estateValueMinor,
            'beneficiaries' => $beneficiaries,
            'decision_path' => $decisionPath,
            'goes_to_crown' => $goesToCrown,
        ];
    }
}
