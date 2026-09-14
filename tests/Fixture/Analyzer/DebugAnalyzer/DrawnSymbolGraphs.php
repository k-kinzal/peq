<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer\DebugAnalyzer;

/**
 * The graph each operation of the recursion contract draws from one portable sequence.
 *
 * A generated graph is reproducible on purpose: peq offers `--debug-seed` so that a
 * graph someone is looking at can be looked at again. What the generators make of a
 * sequence of draws is what that promise rests on, so the whole result of every
 * operation is recorded here for PortableDraws seeded with 29 at depth 2 — a seed
 * whose graphs hold every kind of relation the generators author. The draws come
 * from PortableDraws rather than from Faker because Faker spells one seed differently
 * below PHP 8.3, and a record has to hold on every runtime peq supports.
 *
 * Regenerating this file is a deliberate act. A change here means the graphs peq
 * draws for a given sequence have changed, which is exactly what the promise says
 * will not happen by accident.
 */
final class DrawnSymbolGraphs
{
    /**
     * Names each operation with the graph it draws for seed 29 at depth 2.
     *
     * @return iterable<string, array{string, string, list<string>}> The operation, its root, and its relations
     */
    public static function atSeed29(): iterable
    {
        yield 'classGraph' => ['classGraph', 'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass', [
            'BeataeVeroIllum\RerumCulpaDolorInterface -[declaration-extends]-> UllamAutNihil\TemporaQuiaNihil\OmnisSolutaOdioInterface',
            'BeataeVeroIllum\RerumCulpaDolorInterface -[declaration-method]-> ErrorAmetQuod\MagniFugitClass::sequiSintMethod',
            'BeataeVeroIllum\RerumCulpaDolorInterface -[declaration-method]-> IpsumNihilTempora\TemporaNemo\BeataeAlias\MinusIpsumClass::fugitAnimiIpsumMethod',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[declaration-type-parameter]-> IureAmetNemo\AutAut\QuodEnim\CulpaOmnisClass',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[declaration-type-return]-> MagniErrorOmnis\DolorOdioOdio\AutErrorClass',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[function-call]-> VelitEius\PorroVeroIpsum\NihilIure\minusIpsumMagniFunction',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[instantiation]-> FugitAut\OdioPorro\HarumPorroClass',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[method-call]-> AutError\MagniError\UllamMinus\QuiaEnimIllumClass::temporaVelitSequiMethod',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[static-call]-> ErrorVero\IureMinus\CulpaNihilClass::solutaSequiSolutaMethod',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[static-property-access]-> OmnisOdio\AliasDolorOmnis\OdioSoluta\IpsumQuodClass::harumQuodProperty',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass -[declaration-constant]-> FugitPorro\NemoBeatae\QuiaVero\OdioBeataeClass::QUIA_TEMPORA_RERUM_CONST',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass -[declaration-constant]-> MagniPorroIllum\IpsumEius\EiusIureVelit\MinusCulpaMinusClass::OMNIS_ODIO_CONST',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass -[declaration-implements]-> BeataeVeroIllum\RerumCulpaDolorInterface',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass -[declaration-implements]-> HarumCulpaQuod\BeataeSintDolor\QuodTempora\AmetQuodErrorInterface',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass -[declaration-method]-> DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass -[declaration-method]-> FugitCulpa\SequiNihilClass::omnisVelitAmetMethod',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass -[declaration-method]-> RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass -[declaration-trait-use]-> IllumMagniError\AmetEiusError\NemoNemoAnimiTrait',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass -[declaration-trait-use]-> QuiaQuiaEnim\MagniAnimiNemo\IllumSequi\NemoMagniAmetTrait',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass -[declaration-trait-use]-> TemporaSolutaMinus\VelitEiusUllam\EnimOmnis\AutVelitTrait',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-parameter]-> OdioCulpaOdio\VeroVelitEnim\EiusVelitSolutaInterface',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-parameter]-> SintHarumFugit\EiusUllam\FugitEius\QuodQuiaHarumInterface',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-parameter]-> SolutaFugitMagni\IureQuia\IpsumSintDolorClass',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-return]-> AliasOdio\EiusHarumSequi\MagniEiusAliasInterface',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[method-call]-> VelitDolorIpsum\ErrorErrorTemporaClass::culpaAutNihilMethod',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[property-access]-> IpsumPorro\VelitHarumAliasClass::quiaIpsumEnimProperty',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[static-call]-> AutUllamQuia\AnimiNemo\FugitHarumOdio\EnimRerumClass::aliasFugitAmetMethod',
            'HarumCulpaQuod\BeataeSintDolor\QuodTempora\AmetQuodErrorInterface -[declaration-constant]-> IpsumPorroAut\IpsumOmnis\FugitEnim\FugitQuodClass::BEATAE_ENIM_CULPA_CONST',
            'HarumCulpaQuod\BeataeSintDolor\QuodTempora\AmetQuodErrorInterface -[declaration-constant]-> QuiaUllamNihil\IureEnimClass::EIUS_QUIA_ODIO_CONST',
            'HarumCulpaQuod\BeataeSintDolor\QuodTempora\AmetQuodErrorInterface -[declaration-constant]-> SintAnimiError\HarumCulpaOdio\VelitAnimiPorro\MagniPorroClass::HARUM_ENIM_FUGIT_CONST',
            'HarumCulpaQuod\BeataeSintDolor\QuodTempora\AmetQuodErrorInterface -[declaration-extends]-> PorroHarumVelit\AutPorroNihil\AnimiIpsumMagni\SintOmnisQuodInterface',
            'HarumCulpaQuod\BeataeSintDolor\QuodTempora\AmetQuodErrorInterface -[declaration-method]-> BeataeMinusCulpa\PorroTemporaClass::harumErrorAmetMethod',
            'HarumCulpaQuod\BeataeSintDolor\QuodTempora\AmetQuodErrorInterface -[declaration-method]-> CulpaRerum\OdioAnimiClass::ametIureQuiaMethod',
            'HarumCulpaQuod\BeataeSintDolor\QuodTempora\AmetQuodErrorInterface -[declaration-method]-> NemoAnimiIure\NihilEnimClass::omnisAmetMethod',
            'HarumCulpaQuod\BeataeSintDolor\QuodTempora\AmetQuodErrorInterface -[declaration-method]-> NihilOmnisOmnis\OmnisEnimClass::iureEnimDolorMethod',
            'IllumMagniError\AmetEiusError\NemoNemoAnimiTrait -[declaration-method]-> AutPorro\AutTempora\AliasIpsumCulpaClass::minusSintVelitMethod',
            'IllumMagniError\AmetEiusError\NemoNemoAnimiTrait -[declaration-method]-> EiusFugit\TemporaBeatae\PorroMinus\EnimFugitMagniClass::magniTemporaMagniMethod',
            'IllumMagniError\AmetEiusError\NemoNemoAnimiTrait -[declaration-method]-> PorroIllum\FugitAliasAut\VelitOdio\AnimiOdioFugitClass::harumErrorQuodMethod',
            'IllumMagniError\AmetEiusError\NemoNemoAnimiTrait -[declaration-property]-> AutIpsum\UllamHarum\HarumQuodMinusClass::beataePorroAliasProperty',
            'IllumMagniError\AmetEiusError\NemoNemoAnimiTrait -[declaration-property]-> ErrorQuodUllam\VeroVelitMinusClass::culpaSolutaProperty',
            'QuiaQuiaEnim\MagniAnimiNemo\IllumSequi\NemoMagniAmetTrait -[declaration-method]-> CulpaVero\BeataeEiusClass::ametIureMethod',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[declaration-type-parameter]-> SequiSolutaQuod\IpsumIllumNemo\IpsumDolorInterface',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[declaration-type-return]-> CulpaVero\EiusQuiaDolor\ErrorSequiEnum',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[method-call]-> RerumBeataeIure\QuodFugit\OmnisCulpa\SolutaIllumEiusClass::omnisSolutaMethod',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[static-call]-> CulpaIllum\EiusOdioClass::ametSolutaMethod',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[static-property-access]-> TemporaFugitTempora\CulpaOmnisEnim\QuodNemoClass::aliasBeataeAliasProperty',
            'TemporaSolutaMinus\VelitEiusUllam\EnimOmnis\AutVelitTrait -[declaration-method]-> AliasAmetBeatae\VelitDolor\HarumFugitClass::illumSintIpsumMethod',
            'TemporaSolutaMinus\VelitEiusUllam\EnimOmnis\AutVelitTrait -[declaration-method]-> NemoFugitOdio\SequiDolorClass::fugitNihilMethod',
            'TemporaSolutaMinus\VelitEiusUllam\EnimOmnis\AutVelitTrait -[declaration-method]-> VelitOdioVero\DolorMinus\OmnisEnim\MagniMinusAliasClass::quodOdioVeroMethod',
            'TemporaSolutaMinus\VelitEiusUllam\EnimOmnis\AutVelitTrait -[declaration-property]-> ErrorPorro\MagniOdioClass::nihilSintProperty',
            'TemporaSolutaMinus\VelitEiusUllam\EnimOmnis\AutVelitTrait -[declaration-property]-> FugitRerumSequi\VeroOdioNihilClass::ametAliasProperty',
            'TemporaSolutaMinus\VelitEiusUllam\EnimOmnis\AutVelitTrait -[declaration-property]-> IpsumMagni\IureEnimMinus\SintDolor\PorroAmetClass::fugitOmnisProperty',
        ]];

        yield 'interfaceGraph' => ['interfaceGraph', 'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisInterface', [
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[declaration-type-parameter]-> IureAmetNemo\AutAut\QuodEnim\CulpaOmnisClass',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[declaration-type-return]-> MagniErrorOmnis\DolorOdioOdio\AutErrorClass',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[function-call]-> VelitEius\PorroVeroIpsum\NihilIure\minusIpsumMagniFunction',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[instantiation]-> FugitAut\OdioPorro\HarumPorroClass',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[method-call]-> AutError\MagniError\UllamMinus\QuiaEnimIllumClass::temporaVelitSequiMethod',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[static-call]-> ErrorVero\IureMinus\CulpaNihilClass::solutaSequiSolutaMethod',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[static-property-access]-> OmnisOdio\AliasDolorOmnis\OdioSoluta\IpsumQuodClass::harumQuodProperty',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisInterface -[declaration-method]-> DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisInterface -[declaration-method]-> FugitCulpa\SequiNihilClass::omnisVelitAmetMethod',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisInterface -[declaration-method]-> RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-parameter]-> OdioCulpaOdio\VeroVelitEnim\EiusVelitSolutaInterface',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-parameter]-> SintHarumFugit\EiusUllam\FugitEius\QuodQuiaHarumInterface',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-parameter]-> SolutaFugitMagni\IureQuia\IpsumSintDolorClass',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-return]-> AliasOdio\EiusHarumSequi\MagniEiusAliasInterface',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[method-call]-> VelitDolorIpsum\ErrorErrorTemporaClass::culpaAutNihilMethod',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[property-access]-> IpsumPorro\VelitHarumAliasClass::quiaIpsumEnimProperty',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[static-call]-> AutUllamQuia\AnimiNemo\FugitHarumOdio\EnimRerumClass::aliasFugitAmetMethod',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[declaration-type-parameter]-> SequiSolutaQuod\IpsumIllumNemo\IpsumDolorInterface',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[declaration-type-return]-> CulpaVero\EiusQuiaDolor\ErrorSequiEnum',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[method-call]-> RerumBeataeIure\QuodFugit\OmnisCulpa\SolutaIllumEiusClass::omnisSolutaMethod',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[static-call]-> CulpaIllum\EiusOdioClass::ametSolutaMethod',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[static-property-access]-> TemporaFugitTempora\CulpaOmnisEnim\QuodNemoClass::aliasBeataeAliasProperty',
        ]];

        yield 'traitGraph' => ['traitGraph', 'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisTrait', [
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[declaration-type-parameter]-> IureAmetNemo\AutAut\QuodEnim\CulpaOmnisClass',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[declaration-type-return]-> MagniErrorOmnis\DolorOdioOdio\AutErrorClass',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[function-call]-> VelitEius\PorroVeroIpsum\NihilIure\minusIpsumMagniFunction',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[instantiation]-> FugitAut\OdioPorro\HarumPorroClass',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[method-call]-> AutError\MagniError\UllamMinus\QuiaEnimIllumClass::temporaVelitSequiMethod',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[static-call]-> ErrorVero\IureMinus\CulpaNihilClass::solutaSequiSolutaMethod',
            'DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod -[static-property-access]-> OmnisOdio\AliasDolorOmnis\OdioSoluta\IpsumQuodClass::harumQuodProperty',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisTrait -[declaration-method]-> DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaClass::beataeSintBeataeMethod',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisTrait -[declaration-method]-> FugitCulpa\SequiNihilClass::omnisVelitAmetMethod',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisTrait -[declaration-method]-> RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-parameter]-> OdioCulpaOdio\VeroVelitEnim\EiusVelitSolutaInterface',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-parameter]-> SintHarumFugit\EiusUllam\FugitEius\QuodQuiaHarumInterface',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-parameter]-> SolutaFugitMagni\IureQuia\IpsumSintDolorClass',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[declaration-type-return]-> AliasOdio\EiusHarumSequi\MagniEiusAliasInterface',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[method-call]-> VelitDolorIpsum\ErrorErrorTemporaClass::culpaAutNihilMethod',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[property-access]-> IpsumPorro\VelitHarumAliasClass::quiaIpsumEnimProperty',
            'FugitCulpa\SequiNihilClass::omnisVelitAmetMethod -[static-call]-> AutUllamQuia\AnimiNemo\FugitHarumOdio\EnimRerumClass::aliasFugitAmetMethod',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[declaration-type-parameter]-> SequiSolutaQuod\IpsumIllumNemo\IpsumDolorInterface',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[declaration-type-return]-> CulpaVero\EiusQuiaDolor\ErrorSequiEnum',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[method-call]-> RerumBeataeIure\QuodFugit\OmnisCulpa\SolutaIllumEiusClass::omnisSolutaMethod',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[static-call]-> CulpaIllum\EiusOdioClass::ametSolutaMethod',
            'RerumAmet\EnimPorro\MinusAut\QuodNihilDolorClass::temporaQuiaMethod -[static-property-access]-> TemporaFugitTempora\CulpaOmnisEnim\QuodNemoClass::aliasBeataeAliasProperty',
        ]];

        yield 'enumGraph' => ['enumGraph', 'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisEnum', [
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisEnum -[declaration-enum-case]-> DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSolutaEnum::BEATAE_SINT_BEATAE_CASE',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisEnum -[declaration-enum-case]-> EnimVelitCulpa\RerumVelit\EnimSoluta\AutQuiaAmetEnum::CULPA_ERROR_CASE',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisEnum -[declaration-enum-case]-> OdioMinusAut\OdioAutCulpa\SolutaNemoIllumEnum::ALIAS_VELIT_CASE',
        ]];

        yield 'methodGraph' => ['methodGraph', 'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass::omnisVeroRerumMethod', [
            'AmetSint\PorroFugit\AutEius\NihilDolorRerumClass::quiaIureProperty -[declaration-type-property]-> CulpaVero\EiusQuiaDolor\ErrorSequiEnum',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-constant]-> HarumCulpaQuod\BeataeSintDolor\QuodTempora\AmetQuodErrorClass::QUOD_VERO_SINT_CONST',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-constant]-> TemporaOmnis\IpsumAmetOmnis\AnimiFugit\QuodFugitClass::ALIAS_QUIA_MINUS_CONST',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-extends]-> PorroVero\AliasQuiaMinus\EiusOdio\MagniNihilClass',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-implements]-> EnimEiusIure\VelitPorroHarum\BeataeMinusCulpaInterface',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-implements]-> EnimMinus\ErrorQuodInterface',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-implements]-> NemoQuiaError\RerumAliasAliasInterface',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-implements]-> QuiaOdioTempora\MagniErrorSolutaInterface',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-implements]-> SolutaHarumCulpa\PorroVelitAnimi\RerumMagniPorroInterface',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-method]-> IureVelitNemo\CulpaMinusClass::omnisOdioMethod',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-method]-> MinusQuia\FugitOdioBeatae\QuiaTemporaRerum\SintAutFugitClass::animiOmnisMethod',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-method]-> OdioPorroAlias\OdioRerumIllumClass::odioAliasMethod',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-method]-> SolutaTemporaFugit\UllamCulpaClass::enimEnimMethod',
            'AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass -[declaration-trait-use]-> NemoSolutaUllam\SequiPorroHarum\NemoAut\NihilMagniAnimiTrait',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-constant]-> DolorOmnis\OdioSoluta\IpsumQuodClass::HARUM_QUOD_CONST',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-constant]-> FugitCulpa\SequiNihilClass::OMNIS_VELIT_AMET_CONST',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-constant]-> MinusQuiaNemo\SequiSolutaClass::AUT_BEATAE_ILLUM_CONST',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-constant]-> SequiTemporaFugit\IllumOdioPorro\HarumPorro\SequiTemporaTemporaClass::DOLOR_DOLOR_CONST',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-constant]-> SolutaSequiSoluta\PorroIpsumEnim\UllamTemporaAliasClass::QUIA_SEQUI_ERROR_CONST',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-extends]-> MagniEiusAlias\SequiBeataeNihilClass',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-implements]-> OdioQuodVero\EnimEius\VelitSolutaRerum\IpsumOdioInterface',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-method]-> DolorOdioOdio\AutError\AutCulpaCulpaClass::nemoIllumIllumMethod',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-property]-> MinusCulpaQuia\IllumSoluta\VelitSequi\NihilAutMinusClass::sequiVeroIureProperty',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-property]-> VelitCulpa\RerumVelitClass::enimSolutaProperty',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-trait-use]-> IpsumSintDolor\CulpaNihil\EnimCulpaVelitTrait',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-trait-use]-> RerumFugitEius\QuodQuiaHarum\CulpaEiusSintTrait',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass::omnisVeroRerumMethod -[declaration-type-return]-> DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass::omnisVeroRerumMethod -[instantiation]-> AmetSoluta\QuiaNihilIure\VeroMinusMagni\SequiHarumCulpaClass',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass::omnisVeroRerumMethod -[method-call]-> UllamSequiAut\UllamAnimiClass::porroSolutaMethod',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass::omnisVeroRerumMethod -[property-access]-> AmetSint\PorroFugit\AutEius\NihilDolorRerumClass::quiaIureProperty',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass::omnisVeroRerumMethod -[static-property-access]-> RerumIpsumDolor\IpsumOmnis\IllumNihilMinusClass::rerumSintProperty',
            'RerumIpsumDolor\IpsumOmnis\IllumNihilMinusClass::rerumSintProperty -[declaration-type-property]-> RerumOmnis\SequiSolutaIllumEnum',
            'UllamSequiAut\UllamAnimiClass::porroSolutaMethod -[declaration-type-return]-> NemoVero\HarumOdio\EnimRerumEnum',
            'UllamSequiAut\UllamAnimiClass::porroSolutaMethod -[static-property-access]-> EiusRerum\IpsumPorro\VelitHarumAliasClass::quiaIpsumEnimProperty',
        ]];

        yield 'functionGraph' => ['functionGraph', 'DolorQuia\EnimErrorSint\EnimFugit\veroQuodOmnisFunction', [
            'DolorQuia\EnimErrorSint\EnimFugit\veroQuodOmnisFunction -[declaration-type-parameter]-> OdioOdio\AutErrorClass',
            'DolorQuia\EnimErrorSint\EnimFugit\veroQuodOmnisFunction -[declaration-type-return]-> DolorIpsumOdio\MagniSint\NemoUllam\AmetOdioSoluta',
            'OdioOdio\AutErrorClass -[declaration-constant]-> IllumOdioPorro\HarumPorroClass::SEQUI_TEMPORA_TEMPORA_CONST',
            'OdioOdio\AutErrorClass -[declaration-constant]-> QuiaNemo\SequiSoluta\AutBeataeIllumClass::MINUS_SEQUI_CONST',
            'OdioOdio\AutErrorClass -[declaration-constant]-> VelitIpsumQuod\HarumQuodClass::EIUS_PORRO_CONST',
            'OdioOdio\AutErrorClass -[declaration-extends]-> AliasMinusFugit\QuiaSequiNihil\OmnisVelitAmetClass',
            'OdioOdio\AutErrorClass -[declaration-implements]-> MagniEiusAlias\SequiBeataeNihilInterface',
            'OdioOdio\AutErrorClass -[declaration-method]-> OdioAliasIllum\OdioSolutaClass::ullamCulpaMethod',
            'OdioOdio\AutErrorClass -[declaration-method]-> QuiaAmetRerum\ErrorNihilAnimi\IpsumNihilClass::temporaRerumAutMethod',
            'OdioOdio\AutErrorClass -[declaration-property]-> PorroSequi\IureVeroNihilClass::sintEnimNemoProperty',
            'OdioOdio\AutErrorClass -[declaration-property]-> SolutaSequiSoluta\PorroIpsumEnim\UllamTemporaAliasClass::quiaSequiErrorProperty',
            'OdioOdio\AutErrorClass -[declaration-trait-use]-> OdioQuodVero\EnimEius\VelitSolutaRerum\IpsumOdioTrait',
        ]];

        yield 'propertyGraph' => ['propertyGraph', 'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass::omnisVeroRerumProperty', [
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-constant]-> DolorOmnis\OdioSoluta\IpsumQuodClass::HARUM_QUOD_CONST',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-constant]-> FugitCulpa\SequiNihilClass::OMNIS_VELIT_AMET_CONST',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-constant]-> MinusQuiaNemo\SequiSolutaClass::AUT_BEATAE_ILLUM_CONST',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-constant]-> SequiTemporaFugit\IllumOdioPorro\HarumPorro\SequiTemporaTemporaClass::DOLOR_DOLOR_CONST',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-constant]-> SolutaSequiSoluta\PorroIpsumEnim\UllamTemporaAliasClass::QUIA_SEQUI_ERROR_CONST',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-extends]-> MagniEiusAlias\SequiBeataeNihilClass',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-implements]-> OdioQuodVero\EnimEius\VelitSolutaRerum\IpsumOdioInterface',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-method]-> DolorOdioOdio\AutError\AutCulpaCulpaClass::nemoIllumIllumMethod',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-property]-> MinusCulpaQuia\IllumSoluta\VelitSequi\NihilAutMinusClass::sequiVeroIureProperty',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-property]-> VelitCulpa\RerumVelitClass::enimSolutaProperty',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-trait-use]-> IpsumSintDolor\CulpaNihil\EnimCulpaVelitTrait',
            'DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass -[declaration-trait-use]-> RerumFugitEius\QuodQuiaHarum\CulpaEiusSintTrait',
            'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass::omnisVeroRerumProperty -[declaration-type-property]-> DolorNemo\UllamAmetOdio\IpsumBeataeSint\AnimiRerumClass',
        ]];

        yield 'typeGraph' => ['typeGraph', 'QuiaOdio\ErrorSintInterface', [
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[const-fetch]-> HarumAliasCulpa\HarumFugitHarum\AliasPorroVeroClass::OMNIS_EIUS_CONST',
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[declaration-type-parameter]-> AliasFugit\SolutaFugitMagni\IureQuia\IpsumSintDolor',
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[declaration-type-parameter]-> CulpaOdioCulpa\QuodVeroVelit',
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[declaration-type-parameter]-> EnimNemoTempora\AliasOdio\EiusHarumSequiEnum',
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[declaration-type-parameter]-> SintHarumFugit\EiusUllam\FugitEius\QuodQuiaHarumInterface',
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[declaration-type-parameter]-> VelitDolorIpsum\ErrorErrorTemporaInterface',
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[declaration-type-return]-> HarumQuodOmnis\NihilEnimEiusEnum',
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[function-call]-> QuodIure\PorroRerumHarum\QuiaAnimiIure\nihilQuiaAnimiFunction',
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[instantiation]-> IllumNihilMinus\RerumSint\IureAmet\EiusSolutaClass',
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[method-call]-> IureSequiAut\QuiaBeataeAnimiClass::veroFugitHarumMethod',
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[static-call]-> FugitTempora\IllumVero\IllumEiusClass::velitIpsumMethod',
            'IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod -[static-property-access]-> NihilDolorRerum\QuiaIureClass::aliasMinusOdioProperty',
            'OmnisQuia\BeataeAliasInterface -[declaration-constant]-> EnimEnim\NemoNemoAlias\AliasRerum\EnimQuodSequiClass::QUOD_IURE_NIHIL_CONST',
            'OmnisQuia\BeataeAliasInterface -[declaration-extends]-> MagniIllum\AnimiUllamInterface',
            'OmnisQuia\BeataeAliasInterface -[declaration-method]-> QuiaNihilIure\VeroMinusMagniClass::sequiHarumCulpaMethod',
            'QuiaOdio\ErrorSintInterface -[declaration-extends]-> OmnisQuia\BeataeAliasInterface',
            'QuiaOdio\ErrorSintInterface -[declaration-method]-> IllumOdioPorro\HarumPorroClass::sequiTemporaTemporaMethod',
            'QuiaOdio\ErrorSintInterface -[declaration-method]-> SequiUllam\EiusVeroAut\IpsumOdioClass::magniSintMethod',
            'SequiUllam\EiusVeroAut\IpsumOdioClass::magniSintMethod -[const-fetch]-> OmnisAmetSequi\AnimiCulpaIpsum\OmnisOdio\AliasDolorOmnisClass::ODIO_SOLUTA_CONST',
            'SequiUllam\EiusVeroAut\IpsumOdioClass::magniSintMethod -[declaration-type-parameter]-> AnimiMinus\NihilHarumTempora\AutError\MagniErrorInterface',
            'SequiUllam\EiusVeroAut\IpsumOdioClass::magniSintMethod -[declaration-type-parameter]-> HarumOdioAlias\RerumOdio\RerumUllamCulpa\AmetNemoEnum',
            'SequiUllam\EiusVeroAut\IpsumOdioClass::magniSintMethod -[declaration-type-return]-> RerumEius\MagniQuodAlias\BeataeBeataeMagniEnum',
            'SequiUllam\EiusVeroAut\IpsumOdioClass::magniSintMethod -[function-call]-> SintEiusMinus\ErrorSequiBeatae\eiusMagniFunction',
            'SequiUllam\EiusVeroAut\IpsumOdioClass::magniSintMethod -[method-call]-> IureVeroNihil\SintEnimNemo\TemporaAnimiAmetClass::nemoHarumQuiaMethod',
        ]];

        yield 'constantGraph' => ['constantGraph', 'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisClass::OMNIS_VERO_RERUM_CONST', []];

        yield 'enumCaseGraph' => ['enumCaseGraph', 'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnisEnum::OMNIS_VERO_RERUM_CASE', []];

        yield 'builtinGraph' => ['builtinGraph', 'DolorQuia\EnimErrorSint\EnimFugit\VeroQuodOmnis', []];
    }
}
