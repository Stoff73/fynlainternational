<?php

declare(strict_types=1);

arch('pack does not import from other country packs')
    ->expect('Fynla\Packs\XX')
    ->not->toUse('Fynla\Packs\GB')
    ->not->toUse('Fynla\Packs\ZA')
    ->not->toUse('Fynla\Packs\US')
    ->not->toUse('Fynla\Packs\AU')
    ->not->toUse('Fynla\Packs\IE');
