<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

/**
 * The graph each operation of the recursion contract draws for one seed.
 *
 * A generated graph is reproducible on purpose: peq offers `--debug-seed` so that a
 * graph someone is looking at can be looked at again. That promise is only worth
 * something if it is written down, so the whole result of every operation is
 * recorded here for seed 42 at depth 2.
 *
 * Regenerating this file is a deliberate act. A change here means the graphs peq
 * draws for a given seed have changed, which is exactly what the promise says will
 * not happen by accident.
 */
final class DrawnSymbolGraphs
{
    /**
     * Names each operation with the graph it draws for seed 42 at depth 2.
     *
     * @return iterable<string, array{string, string, list<string>}> The operation, its root, and its relations
     */
    public static function atSeed42(): iterable
    {
        yield 'classGraph' => ['classGraph', 'VoluptatumDoloremCommodi\VelitEstVelClass', [
            'AliasItaque\LaborumEstInterface -[declaration-constant]-> ConsequaturUt\VoluptatemMollitiaOfficiisClass::ET_ELIGENDI_INCIDUNT_CONST',
            'AliasItaque\LaborumEstInterface -[declaration-constant]-> MinimaAt\NonDolorClass::DESERUNT_QUAE_SIT_CONST',
            'AliasItaque\LaborumEstInterface -[declaration-constant]-> OditDolorem\VoluptatesRationeA\PorroUtAdClass::PARIATUR_EIUS_CONST',
            'AliasItaque\LaborumEstInterface -[declaration-constant]-> QuiIpsa\VoluptatemUt\AliasMolestiasEarumClass::QUIA_EX_DOLOREM_CONST',
            'AliasItaque\LaborumEstInterface -[declaration-method]-> LaborumQuiTempore\EtConsequatur\NostrumEsseClass::omnisOmnisDoloremMethod',
            'AliasItaque\LaborumEstInterface -[declaration-method]-> OfficiisCupiditateEt\ConsequaturQui\VoluptateAliquamArchitecto\NostrumVelSedClass::nobisQuisCumMethod',
            'AliasItaque\LaborumEstInterface -[declaration-method]-> QuisADistinctio\ConsequaturVoluptatem\QuiaNihilOdio\QuiHarumClass::commodiBlanditiisMethod',
            'DelenitiUtEt\ExercitationemVeritatisTrait -[declaration-property]-> DolorAutemUt\LaboriosamPorroAut\EstVelitEum\AEumClass::idEtEligendiProperty',
            'DelenitiUtEt\ExercitationemVeritatisTrait -[declaration-property]-> EaqueVoluptatemUt\VoluptateNesciunt\QuiSunt\HicVoluptasClass::etSuntConsecteturProperty',
            'DelenitiUtEt\ExercitationemVeritatisTrait -[declaration-property]-> FugitVoluptasNihil\AnimiNon\UtVoluptas\AbIdClass::quiaVeroProperty',
            'DelenitiUtEt\ExercitationemVeritatisTrait -[declaration-property]-> QuiaAssumendaAut\CorruptiCulpaClass::suscipitOptioAdipisciProperty',
            'DelenitiUtEt\ExercitationemVeritatisTrait -[declaration-property]-> VelIste\SaepeSapienteQuidemClass::illumEtRemProperty',
            'EosEtIste\PorroInventore\DebitisUtNamTrait -[declaration-method]-> CumqueExercitationem\VelitMollitiaVitae\VoluptasNonClass::omnisEnimMethod',
            'EosEtIste\PorroInventore\DebitisUtNamTrait -[declaration-method]-> TemporibusAdipisciQuos\ExpeditaQuia\DoloremNemo\QuodSitVelitClass::repudiandaeExcepturiTotamMethod',
            'EosEtIste\PorroInventore\DebitisUtNamTrait -[declaration-property]-> AmetEos\VelitDoloresAutemClass::velitDoloremOfficiaProperty',
            'EosEtIste\PorroInventore\DebitisUtNamTrait -[declaration-property]-> EumAut\EtFugiatFacilisClass::facereMollitiaProperty',
            'EosEtIste\PorroInventore\DebitisUtNamTrait -[declaration-property]-> PariaturCorporis\VoluptatibusQuaeratEumClass::autOccaecatiSuscipitProperty',
            'EosEtIste\PorroInventore\DebitisUtNamTrait -[declaration-property]-> SuntUt\RerumEnimRem\EiusMollitiaClass::voluptatemAccusamusProperty',
            'EstLaboriosam\SedArchitectoEtTrait -[declaration-property]-> DolorSuscipitLaudantium\VoluptasMinusAut\EtVoluptatibusEtClass::quiaExercitationemProperty',
            'EstLaboriosam\SedArchitectoEtTrait -[declaration-property]-> EtFacilisEligendi\BeataeErrorVoluptates\NesciuntAutem\EiusPlaceatClass::culpaEtProperty',
            'EstLaboriosam\SedArchitectoEtTrait -[declaration-property]-> NihilDolore\IllumQuiEsseClass::consequaturEaqueProperty',
            'EstLaboriosam\SedArchitectoEtTrait -[declaration-property]-> VelitIllumOmnis\RationeQuoVoluptatum\DelenitiImpeditDignissimosClass::eumQuisquamProperty',
            'EstLaboriosam\SedArchitectoEtTrait -[declaration-property]-> VelitRecusandae\QuisquamEtTempore\VoluptatesLaudantiumNonClass::etQuisquamHarumProperty',
            'InciduntUt\QuisNam\OditQuasClass::corporisQuiaProperty -[declaration-type-property]-> ExcepturiIure\QuaeratHic\NequeCorruptiAtqueInterface',
            'IpsumCulpa\FacereNonAutem\OfficiaPorro\SequiConsequaturVoluptasTrait -[declaration-method]-> AbUt\EtOmnis\MaximeDoloresTempore\DelectusCumEstClass::debitisRepellatMethod',
            'IpsumCulpa\FacereNonAutem\OfficiaPorro\SequiConsequaturVoluptasTrait -[declaration-method]-> InventoreDucimus\AdEum\QuiaNecessitatibusClass::quiIsteMethod',
            'IpsumCulpa\FacereNonAutem\OfficiaPorro\SequiConsequaturVoluptasTrait -[declaration-method]-> RepudiandaeNobisPraesentium\EsseUtQuae\AutAut\CumAsperioresClass::nonNequeMethod',
            'IpsumCulpa\FacereNonAutem\OfficiaPorro\SequiConsequaturVoluptasTrait -[declaration-method]-> SintEveniet\DolorePerferendisNulla\ConsequaturOmnis\OmnisTemporaRepellendusClass::quisAssumendaTeneturMethod',
            'IpsumCulpa\FacereNonAutem\OfficiaPorro\SequiConsequaturVoluptasTrait -[declaration-method]-> VoluptatemConsequatur\NemoTemporibusIpsam\FacereAliquidClass::quasiEtEtMethod',
            'IpsumCulpa\FacereNonAutem\OfficiaPorro\SequiConsequaturVoluptasTrait -[declaration-property]-> InventoreVoluptatem\ImpeditRerum\QuiVoluptateNisiClass::errorQuiaVoluptatemProperty',
            'IpsumCulpa\FacereNonAutem\OfficiaPorro\SequiConsequaturVoluptasTrait -[declaration-property]-> IpsamSed\NisiAccusamus\DolorEiusQuidem\VoluptatemEstClass::blanditiisExcepturiRecusandaeProperty',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[const-fetch]-> MaximeQuasQui\SapienteInventorePossimus\IpsumAutQuiaClass::EXCEPTURI_QUAM_ET_CONST',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-parameter]-> ACumqueNobis\QuiaVero\EstDelenitiExplicaboClass',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-parameter]-> EtMolestiaeAut\DoloremQuidemFugit\ConsequaturVelitVoluptatemInterface',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-parameter]-> NecessitatibusVero\IustoVitaeIpsamInterface',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-return]-> VoluptatemEos\IustoAutem\VelitDolorem',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[function-call]-> AAut\ReiciendisEsse\QuibusdamNisi\voluptasConsequaturFunction',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[method-call]-> ModiAnimiAut\AutVoluptates\HarumNon\VelSedClass::adEtReiciendisMethod',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[static-property-access]-> SuntVelitFacilis\QuodTotamAliquidClass::voluptasVoluptateProperty',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[declaration-type-parameter]-> NihilVelConsequuntur\EtEligendiPossimus\EarumVoluptasQuas\VoluptatibusSuscipitSedInterface',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[declaration-type-return]-> DebitisNisiProvident\VeroNamEt',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[instantiation]-> IureDoloremque\IdLaboreClass',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[method-call]-> EiusSintSimilique\NonNecessitatibusPossimus\ModiOmnisClass::molestiaeOdioMethod',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[property-access]-> ReiciendisEnim\SintRationeTotam\AsperioresUtFugiat\DoloremAtClass::idNatusProperty',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[static-call]-> UtFugiat\EumNisiAutClass::optioDolorumMethod',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[static-property-access]-> DistinctioEst\AbQui\EumNonSed\OfficiisSuscipitClass::delenitiExpeditaProperty',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[declaration-type-parameter]-> VoluptatibusTenetur\MolestiasDelectusInterface',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[declaration-type-return]-> TemporeSedQuaerat\QuiLaborum\AutDolorInInterface',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[function-call]-> NihilEsseAut\IdReprehenderit\IureNisiVoluptas\sintImpeditAmetFunction',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[instantiation]-> AutAutem\AtqueSuscipit\VoluptateTemporeUtClass',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[static-call]-> CumqueAccusamusMaxime\EtConsequaturLaboriosamClass::delenitiEaqueMethod',
            'QuiExpeditaItaque\InQuamOmnis\SitInInterface -[declaration-constant]-> AdipisciQui\MinimaOmnisClass::VERITATIS_CONSEQUATUR_CONST',
            'QuiExpeditaItaque\InQuamOmnis\SitInInterface -[declaration-constant]-> CommodiOdioConsequatur\VeroQuibusdam\DoloribusVoluptatemClass::EVENIET_VITAE_CONST',
            'QuiExpeditaItaque\InQuamOmnis\SitInInterface -[declaration-constant]-> QuiaAut\UtSequi\QuiConsecteturClass::SINT_UNDE_ACCUSANTIUM_CONST',
            'QuiExpeditaItaque\InQuamOmnis\SitInInterface -[declaration-constant]-> UndeExplicabo\IpsumEtCommodiClass::QUAE_MODI_CONST',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-constant]-> EtIusto\RepellendusIllumUllam\EaqueUtClass::HIC_REPELLAT_CONST',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-constant]-> NostrumQuia\NullaEligendiError\BeataeAliasSit\TeneturQuiClass::VOLUPTATEM_DOLORIBUS_CONST',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-constant]-> QuaeBlanditiisArchitecto\MolestiaeBeataeQui\TemporeOmnisEt\NobisOccaecatiClass::EVENIET_SAEPE_CONST',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-constant]-> QuaeratIllum\VelEtAperiam\OptioEnimNobis\QuaeQuiErrorClass::COMMODI_ET_CONST',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-constant]-> VeritatisDistinctio\VelFugitLaudantium\PraesentiumOdioEa\QuasExercitationemLaudantiumClass::REPELLAT_DOLOREM_CONST',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-extends]-> AsperioresFacere\AdIpsumQuia\MolestiaeUtPorro\IdOmnisAutemClass',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-implements]-> OditCorporis\EiusVoluptasSunt\BeataePerferendisInterface',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-method]-> EtNisi\AutemNostrumAliasClass::placeatCorruptiSuscipitMethod',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-method]-> RemRepellendusVoluptatem\ErrorOmnis\VoluptatesAbQuo\EtNecessitatibusVoluptatesClass::autVoluptatemEtMethod',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-property]-> AtqueModiUnde\DictaQuia\VeritatisEt\MagnamSitNemoClass::occaecatiAutProperty',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-property]-> EumEt\QuoError\OmnisUtClass::laudantiumAnimiProperty',
            'QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass -[declaration-property]-> QuoUtArchitecto\IustoAnimiLiberoClass::dolorEosIllumProperty',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[const-fetch]-> QuasiNamQuisquam\NihilUt\UtOdioDolore\FugitSintClass::VEL_ET_OMNIS_CONST',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[declaration-type-return]-> NonVelit\MolestiaeExplicaboIllo\DoloremFacilisVero',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[instantiation]-> QuisquamNonDeleniti\PerferendisUtOfficiaClass',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[method-call]-> ReiciendisEstExercitationem\QuaeConsequatur\ExpeditaOditClass::dictaCorruptiMethod',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[static-property-access]-> AssumendaDolor\InExpedita\DoloremSunt\IpsumNihilClass::omnisQuiEnimProperty',
            'QuodTemporeMagni\ItaqueNatusBeatae\EligendiDolorumVoluptasClass::utSintProperty -[declaration-type-property]-> VoluptatemOmnisLaborum\AdEstInterface',
            'VeniamAssumenda\OditAssumendaConsectetur\ExcepturiBlanditiisTrait -[declaration-method]-> OmnisPraesentiumVeniam\FugitEtEsse\AliquidImpedit\BlanditiisVelitClass::voluptatesEumMethod',
            'VeniamAssumenda\OditAssumendaConsectetur\ExcepturiBlanditiisTrait -[declaration-method]-> QuiNesciunt\OptioOfficiisPraesentiumClass::quoDoloremQuiMethod',
            'VeniamAssumenda\OditAssumendaConsectetur\ExcepturiBlanditiisTrait -[declaration-method]-> RepellatCorrupti\EtSint\DoloremInciduntSaepeClass::voluptatesNonAMethod',
            'VeniamAssumenda\OditAssumendaConsectetur\ExcepturiBlanditiisTrait -[declaration-method]-> TotamQuo\EosExplicabo\EaAClass::nesciuntOmnisMethod',
            'VeniamAssumenda\OditAssumendaConsectetur\ExcepturiBlanditiisTrait -[declaration-method]-> VoluptatemNulla\ConsequaturDoloremClass::suntMaximeMethod',
            'VeniamAssumenda\OditAssumendaConsectetur\ExcepturiBlanditiisTrait -[declaration-property]-> AutPerspiciatis\EumNecessitatibusQui\QuaeExpeditaClass::delenitiEnimRepellatProperty',
            'VeniamAssumenda\OditAssumendaConsectetur\ExcepturiBlanditiisTrait -[declaration-property]-> EstIsteOmnis\OfficiaSed\PariaturAdipisciClass::assumendaSedProperty',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-extends]-> QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-implements]-> AliasItaque\LaborumEstInterface',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-implements]-> QuiExpeditaItaque\InQuamOmnis\SitInInterface',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-method]-> IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-method]-> LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-method]-> NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-method]-> QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-property]-> InciduntUt\QuisNam\OditQuasClass::corporisQuiaProperty',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-property]-> QuodTemporeMagni\ItaqueNatusBeatae\EligendiDolorumVoluptasClass::utSintProperty',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-trait-use]-> DelenitiUtEt\ExercitationemVeritatisTrait',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-trait-use]-> EosEtIste\PorroInventore\DebitisUtNamTrait',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-trait-use]-> EstLaboriosam\SedArchitectoEtTrait',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-trait-use]-> IpsumCulpa\FacereNonAutem\OfficiaPorro\SequiConsequaturVoluptasTrait',
            'VoluptatumDoloremCommodi\VelitEstVelClass -[declaration-trait-use]-> VeniamAssumenda\OditAssumendaConsectetur\ExcepturiBlanditiisTrait',
        ]];

        yield 'interfaceGraph' => ['interfaceGraph', 'VoluptatumDoloremCommodi\VelitEstVelInterface', [
            'DolorumVoluptas\UtSintInterface -[declaration-constant]-> EtNisi\AutemNostrumAliasClass::PLACEAT_CORRUPTI_SUSCIPIT_CONST',
            'DolorumVoluptas\UtSintInterface -[declaration-constant]-> ExcepturiQuoUt\AnimiIustoClass::LIBERO_NEQUE_DOLOR_CONST',
            'DolorumVoluptas\UtSintInterface -[declaration-method]-> SintRem\VoluptatemEumError\AmetVoluptates\QuoVelEtClass::voluptatesNostrumMethod',
            'DolorumVoluptas\UtSintInterface -[declaration-method]-> UtAut\IpsamQuasiClass::fugitFugitMethod',
            'DolorumVoluptas\UtSintInterface -[declaration-method]-> VoluptatemOmnisLaborum\AdEstClass::repellendusQuiMethod',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[const-fetch]-> MaximeQuasQui\SapienteInventorePossimus\IpsumAutQuiaClass::EXCEPTURI_QUAM_ET_CONST',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-parameter]-> ACumqueNobis\QuiaVero\EstDelenitiExplicaboClass',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-parameter]-> EtMolestiaeAut\DoloremQuidemFugit\ConsequaturVelitVoluptatemInterface',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-parameter]-> NecessitatibusVero\IustoVitaeIpsamInterface',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-return]-> VoluptatemEos\IustoAutem\VelitDolorem',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[function-call]-> AAut\ReiciendisEsse\QuibusdamNisi\voluptasConsequaturFunction',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[method-call]-> ModiAnimiAut\AutVoluptates\HarumNon\VelSedClass::adEtReiciendisMethod',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[static-property-access]-> SuntVelitFacilis\QuodTotamAliquidClass::voluptasVoluptateProperty',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[declaration-type-parameter]-> NihilVelConsequuntur\EtEligendiPossimus\EarumVoluptasQuas\VoluptatibusSuscipitSedInterface',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[declaration-type-return]-> DebitisNisiProvident\VeroNamEt',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[instantiation]-> IureDoloremque\IdLaboreClass',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[method-call]-> EiusSintSimilique\NonNecessitatibusPossimus\ModiOmnisClass::molestiaeOdioMethod',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[property-access]-> ReiciendisEnim\SintRationeTotam\AsperioresUtFugiat\DoloremAtClass::idNatusProperty',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[static-call]-> UtFugiat\EumNisiAutClass::optioDolorumMethod',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[static-property-access]-> DistinctioEst\AbQui\EumNonSed\OfficiisSuscipitClass::delenitiExpeditaProperty',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[declaration-type-parameter]-> VoluptatibusTenetur\MolestiasDelectusInterface',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[declaration-type-return]-> TemporeSedQuaerat\QuiLaborum\AutDolorInInterface',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[function-call]-> NihilEsseAut\IdReprehenderit\IureNisiVoluptas\sintImpeditAmetFunction',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[instantiation]-> AutAutem\AtqueSuscipit\VoluptateTemporeUtClass',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[static-call]-> CumqueAccusamusMaxime\EtConsequaturLaboriosamClass::delenitiEaqueMethod',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[const-fetch]-> QuasiNamQuisquam\NihilUt\UtOdioDolore\FugitSintClass::VEL_ET_OMNIS_CONST',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[declaration-type-return]-> NonVelit\MolestiaeExplicaboIllo\DoloremFacilisVero',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[instantiation]-> QuisquamNonDeleniti\PerferendisUtOfficiaClass',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[method-call]-> ReiciendisEstExercitationem\QuaeConsequatur\ExpeditaOditClass::dictaCorruptiMethod',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[static-property-access]-> AssumendaDolor\InExpedita\DoloremSunt\IpsumNihilClass::omnisQuiEnimProperty',
            'VoluptatumDoloremCommodi\VelitEstVelInterface -[declaration-constant]-> CorruptiAtqueEt\RerumNemoQuasi\EstDucimusTemporibus\RepellendusDoloremTeneturClass::QUIS_EXERCITATIONEM_NAM_CONST',
            'VoluptatumDoloremCommodi\VelitEstVelInterface -[declaration-constant]-> InciduntUt\QuisNam\OditQuasClass::CORPORIS_QUIA_CONST',
            'VoluptatumDoloremCommodi\VelitEstVelInterface -[declaration-extends]-> DolorumVoluptas\UtSintInterface',
            'VoluptatumDoloremCommodi\VelitEstVelInterface -[declaration-method]-> IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod',
            'VoluptatumDoloremCommodi\VelitEstVelInterface -[declaration-method]-> LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod',
            'VoluptatumDoloremCommodi\VelitEstVelInterface -[declaration-method]-> NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod',
            'VoluptatumDoloremCommodi\VelitEstVelInterface -[declaration-method]-> QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod',
        ]];

        yield 'traitGraph' => ['traitGraph', 'VoluptatumDoloremCommodi\VelitEstVelTrait', [
            'InciduntUt\QuisNam\OditQuasClass::corporisQuiaProperty -[declaration-type-property]-> ExcepturiIure\QuaeratHic\NequeCorruptiAtqueInterface',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[const-fetch]-> MaximeQuasQui\SapienteInventorePossimus\IpsumAutQuiaClass::EXCEPTURI_QUAM_ET_CONST',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-parameter]-> ACumqueNobis\QuiaVero\EstDelenitiExplicaboClass',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-parameter]-> EtMolestiaeAut\DoloremQuidemFugit\ConsequaturVelitVoluptatemInterface',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-parameter]-> NecessitatibusVero\IustoVitaeIpsamInterface',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[declaration-type-return]-> VoluptatemEos\IustoAutem\VelitDolorem',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[function-call]-> AAut\ReiciendisEsse\QuibusdamNisi\voluptasConsequaturFunction',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[method-call]-> ModiAnimiAut\AutVoluptates\HarumNon\VelSedClass::adEtReiciendisMethod',
            'IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod -[static-property-access]-> SuntVelitFacilis\QuodTotamAliquidClass::voluptasVoluptateProperty',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[declaration-type-parameter]-> NihilVelConsequuntur\EtEligendiPossimus\EarumVoluptasQuas\VoluptatibusSuscipitSedInterface',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[declaration-type-return]-> DebitisNisiProvident\VeroNamEt',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[instantiation]-> IureDoloremque\IdLaboreClass',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[method-call]-> EiusSintSimilique\NonNecessitatibusPossimus\ModiOmnisClass::molestiaeOdioMethod',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[property-access]-> ReiciendisEnim\SintRationeTotam\AsperioresUtFugiat\DoloremAtClass::idNatusProperty',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[static-call]-> UtFugiat\EumNisiAutClass::optioDolorumMethod',
            'LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod -[static-property-access]-> DistinctioEst\AbQui\EumNonSed\OfficiisSuscipitClass::delenitiExpeditaProperty',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[declaration-type-parameter]-> VoluptatibusTenetur\MolestiasDelectusInterface',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[declaration-type-return]-> TemporeSedQuaerat\QuiLaborum\AutDolorInInterface',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[function-call]-> NihilEsseAut\IdReprehenderit\IureNisiVoluptas\sintImpeditAmetFunction',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[instantiation]-> AutAutem\AtqueSuscipit\VoluptateTemporeUtClass',
            'NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod -[static-call]-> CumqueAccusamusMaxime\EtConsequaturLaboriosamClass::delenitiEaqueMethod',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[const-fetch]-> QuasiNamQuisquam\NihilUt\UtOdioDolore\FugitSintClass::VEL_ET_OMNIS_CONST',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[declaration-type-return]-> NonVelit\MolestiaeExplicaboIllo\DoloremFacilisVero',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[instantiation]-> QuisquamNonDeleniti\PerferendisUtOfficiaClass',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[method-call]-> ReiciendisEstExercitationem\QuaeConsequatur\ExpeditaOditClass::dictaCorruptiMethod',
            'QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod -[static-property-access]-> AssumendaDolor\InExpedita\DoloremSunt\IpsumNihilClass::omnisQuiEnimProperty',
            'QuodTemporeMagni\ItaqueNatusBeatae\EligendiDolorumVoluptasClass::utSintProperty -[declaration-type-property]-> VoluptatemOmnisLaborum\AdEstInterface',
            'VoluptatumDoloremCommodi\VelitEstVelTrait -[declaration-method]-> IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::quiaDistinctioArchitectoMethod',
            'VoluptatumDoloremCommodi\VelitEstVelTrait -[declaration-method]-> LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumClass::estMaximeEtMethod',
            'VoluptatumDoloremCommodi\VelitEstVelTrait -[declaration-method]-> NobisRem\SaepeNullaAtque\CulpaRerum\NequeAutAssumendaClass::dictaDelectusMethod',
            'VoluptatumDoloremCommodi\VelitEstVelTrait -[declaration-method]-> QuiSequiSaepe\NonOditQui\EumSintClass::voluptatemExplicaboNonMethod',
            'VoluptatumDoloremCommodi\VelitEstVelTrait -[declaration-property]-> InciduntUt\QuisNam\OditQuasClass::corporisQuiaProperty',
            'VoluptatumDoloremCommodi\VelitEstVelTrait -[declaration-property]-> QuodTemporeMagni\ItaqueNatusBeatae\EligendiDolorumVoluptasClass::utSintProperty',
        ]];

        yield 'enumGraph' => ['enumGraph', 'VoluptatumDoloremCommodi\VelitEstVelEnum', [
            'VoluptatumDoloremCommodi\VelitEstVelEnum -[declaration-enum-case]-> ArchitectoMolestiaeOdio\QuosConsequunturAtque\SedCommodiLaborumEnum::ERROR_DEBITIS_EST_CASE',
            'VoluptatumDoloremCommodi\VelitEstVelEnum -[declaration-enum-case]-> LaudantiumOmnis\DolorEtEnum::ET_OMNIS_CASE',
            'VoluptatumDoloremCommodi\VelitEstVelEnum -[declaration-enum-case]-> LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumEnum::EST_MAXIME_ET_CASE',
            'VoluptatumDoloremCommodi\VelitEstVelEnum -[declaration-enum-case]-> MinimaVoluptatibus\SedOccaecati\MollitiaEosOptioEnum::SIT_EVENIET_LABORUM_CASE',
        ]];

        yield 'methodGraph' => ['methodGraph', 'VoluptatumDoloremCommodi\VelitEstVelClass::molestiaeOfficiisUtMethod', [
            'ConsequunturQui\AnimiEtPerferendis\NonRerumClass::estOccaecatiUtProperty -[declaration-type-property]-> SedFugitSint\VelEtOmnis\EligendiEnimEnum',
            'DolorNon\SitError\SuntEtSolutaInterface -[declaration-constant]-> AutemDoloribus\SuscipitAtVoluptate\UtRemAut\DeseruntExplicaboAutemClass::ODIT_AB_QUIS_CONST',
            'DolorNon\SitError\SuntEtSolutaInterface -[declaration-constant]-> IdReprehenderit\IureNisiVoluptas\SintImpeditAmet\CumqueTemporibusEsseClass::CORRUPTI_MOLESTIAS_AUT_CONST',
            'DolorNon\SitError\SuntEtSolutaInterface -[declaration-constant]-> IsteAbSuscipit\OmnisQuibusdam\VoluptatumSitEstClass::QUIA_DISTINCTIO_ARCHITECTO_CONST',
            'DolorNon\SitError\SuntEtSolutaInterface -[declaration-constant]-> SuntEt\ExercitationemMolestiaeFugiatClass::CUPIDITATE_QUIA_CONST',
            'DolorNon\SitError\SuntEtSolutaInterface -[declaration-extends]-> InEaSequi\EumAccusamus\EarumInventoreUt\IpsumUtInterface',
            'OditQuas\CorporisQuiaClass -[declaration-constant]-> QuiIpsamQuasi\FugitFugit\DoloremVoluptatemSapiente\FugiatDoloresClass::ATQUE_QUASI_OMNIS_CONST',
            'OditQuas\CorporisQuiaClass -[declaration-constant]-> VoluptatemEt\AtqueUtClass::CONSEQUATUR_LAUDANTIUM_CONST',
            'OditQuas\CorporisQuiaClass -[declaration-implements]-> ConsequaturImpedit\IlloQuam\SuntQuiaInterface',
            'OditQuas\CorporisQuiaClass -[declaration-implements]-> DolorEosIllum\MaximeSimilique\NostrumEtInterface',
            'OditQuas\CorporisQuiaClass -[declaration-implements]-> EtConsequuntur\SitNemoInterface',
            'OditQuas\CorporisQuiaClass -[declaration-method]-> EtLaudantiumVoluptatem\LaborumLaudantium\EstUtClass::quiVoluptateMethod',
            'OditQuas\CorporisQuiaClass -[declaration-method]-> ExcepturiIure\QuaeratHic\NequeCorruptiAtqueClass::nobisRerumNemoMethod',
            'OditQuas\CorporisQuiaClass -[declaration-method]-> QuaeQui\IureQuod\MagniPlaceatClass::natusBeataeMethod',
            'OditQuas\CorporisQuiaClass -[declaration-trait-use]-> OptioAutemQuo\SolutaEt\EtFacereQuoTrait',
            'OmnisFacilis\TotamDicta\EnimQuia\RationeNihilVelClass -[declaration-constant]-> AtQuis\NatusUtVoluptasClass::NON_DOLORUM_EUM_CONST',
            'OmnisFacilis\TotamDicta\EnimQuia\RationeNihilVelClass -[declaration-constant]-> AutIllumOptio\AsperioresOfficiis\IdSoluta\LaudantiumEstClass::RERUM_MOLLITIA_VITAE_CONST',
            'OmnisFacilis\TotamDicta\EnimQuia\RationeNihilVelClass -[declaration-constant]-> NemoDolor\OmnisEtClass::QUAM_VOLUPTATEM_CUM_CONST',
            'OmnisFacilis\TotamDicta\EnimQuia\RationeNihilVelClass -[declaration-constant]-> NullaAtquePariatur\RerumQuiNequeClass::ASSUMENDA_FUGIT_DICTA_CONST',
            'OmnisFacilis\TotamDicta\EnimQuia\RationeNihilVelClass -[declaration-constant]-> SedRem\SuscipitNam\ExpeditaExplicaboEa\VoluptatemDoloresClass::NECESSITATIBUS_IN_ILLO_CONST',
            'OmnisFacilis\TotamDicta\EnimQuia\RationeNihilVelClass -[declaration-extends]-> LaborumExercitationem\DolorInIpsum\QuaeNamSimiliqueClass',
            'OmnisFacilis\TotamDicta\EnimQuia\RationeNihilVelClass -[declaration-implements]-> MolestiasDelectus\RemReiciendisInterface',
            'OmnisFacilis\TotamDicta\EnimQuia\RationeNihilVelClass -[declaration-method]-> PossimusEa\OmnisArchitectoMolestiaeClass::quisquamQuosMethod',
            'OmnisFacilis\TotamDicta\EnimQuia\RationeNihilVelClass -[declaration-method]-> SuscipitSed\EtMollitiaClass::optioAutSitMethod',
            'SapientePraesentium\EnimSaepe\NamMinimaClass::nonEvenietIdMethod -[const-fetch]-> DictaCorrupti\QuiEtRerum\VoluptatumQuasiOmnis\VeroMollitiaClass::DOLOR_EA_NULLA_CONST',
            'SapientePraesentium\EnimSaepe\NamMinimaClass::nonEvenietIdMethod -[declaration-type-parameter]-> SedInAd\ReiciendisIustoQui\InventoreEtAliquamInterface',
            'SapientePraesentium\EnimSaepe\NamMinimaClass::nonEvenietIdMethod -[declaration-type-return]-> LaborumDoloresLaudantium\OmnisVeritatis\TeneturQui',
            'SapientePraesentium\EnimSaepe\NamMinimaClass::nonEvenietIdMethod -[function-call]-> SintIpsumAut\ArchitectoExcepturiQuam\QuiReiciendisDicta\nesciuntDoloribusFunction',
            'SapientePraesentium\EnimSaepe\NamMinimaClass::nonEvenietIdMethod -[method-call]-> VitaeQuibusdamNisi\VoluptasConsequaturClass::veniamDeseruntBeataeMethod',
            'SapientePraesentium\EnimSaepe\NamMinimaClass::nonEvenietIdMethod -[property-access]-> SequiSaepeHic\OditQuiLibero\SintIpsaClass::explicaboNonSapienteProperty',
            'SapientePraesentium\EnimSaepe\NamMinimaClass::nonEvenietIdMethod -[static-call]-> TotamAliquid\VoluptasVoluptate\NemoUtClass::aliquamQuiaDoloribusMethod',
            'SapientePraesentium\EnimSaepe\NamMinimaClass::nonEvenietIdMethod -[static-property-access]-> SitIpsum\AIpsumSed\NonNecessitatibusClass::consequaturAbProperty',
            'VoluptatumDoloremCommodi\VelitEstVelClass::molestiaeOfficiisUtMethod -[const-fetch]-> ErrorEt\EumRerum\SedIsteRerumClass::NESCIUNT_QUIA_ILLUM_CONST',
            'VoluptatumDoloremCommodi\VelitEstVelClass::molestiaeOfficiisUtMethod -[declaration-type-parameter]-> DolorNon\SitError\SuntEtSolutaInterface',
            'VoluptatumDoloremCommodi\VelitEstVelClass::molestiaeOfficiisUtMethod -[declaration-type-parameter]-> OmnisFacilis\TotamDicta\EnimQuia\RationeNihilVelClass',
            'VoluptatumDoloremCommodi\VelitEstVelClass::molestiaeOfficiisUtMethod -[declaration-type-return]-> AnimiConsequaturDolore\SimiliqueEarumUt\MaximeEtSed\SitAut',
            'VoluptatumDoloremCommodi\VelitEstVelClass::molestiaeOfficiisUtMethod -[instantiation]-> OditQuas\CorporisQuiaClass',
            'VoluptatumDoloremCommodi\VelitEstVelClass::molestiaeOfficiisUtMethod -[property-access]-> ConsequunturQui\AnimiEtPerferendis\NonRerumClass::estOccaecatiUtProperty',
            'VoluptatumDoloremCommodi\VelitEstVelClass::molestiaeOfficiisUtMethod -[static-call]-> SapientePraesentium\EnimSaepe\NamMinimaClass::nonEvenietIdMethod',
        ]];

        yield 'functionGraph' => ['functionGraph', 'VoluptatumDoloremCommodi\velitEstVelFunction', [
            'AmetLaudantiumOmnis\DolorEt\etOmnisFunction -[declaration-type-parameter]-> OccaecatiAsperioresUt\MinimaDoloremClass',
            'AmetLaudantiumOmnis\DolorEt\etOmnisFunction -[declaration-type-parameter]-> SintSimilique\NonNecessitatibusPossimus\ModiOmnis',
            'AmetLaudantiumOmnis\DolorEt\etOmnisFunction -[declaration-type-parameter]-> UtEum\AutIllumOptio\AsperioresOfficiis\IdSolutaClass',
            'AmetLaudantiumOmnis\DolorEt\etOmnisFunction -[declaration-type-return]-> EtEligendiPossimus\EarumVoluptasQuas\VoluptatibusSuscipitSedInterface',
            'AmetLaudantiumOmnis\DolorEt\etOmnisFunction -[function-call]-> QuisOmnis\ConsequaturDistinctioEst\AbQui\eumNonSedFunction',
            'VoluptatumDoloremCommodi\velitEstVelFunction -[declaration-type-return]-> LiberoVitae\QuiAnimiConsequatur\UtSimiliqueEarumEnum',
            'VoluptatumDoloremCommodi\velitEstVelFunction -[function-call]-> AmetLaudantiumOmnis\DolorEt\etOmnisFunction',
        ]];

        yield 'propertyGraph' => ['propertyGraph', 'VoluptatumDoloremCommodi\VelitEstVelClass::molestiaeOfficiisUtProperty', [
            'VoluptatumDoloremCommodi\VelitEstVelClass::molestiaeOfficiisUtProperty -[declaration-type-property]-> AnimiConsequaturDolore\SimiliqueEarumUt\MaximeEtSed\SitAut',
        ]];

        yield 'typeGraph' => ['typeGraph', 'DoloremCommodi\VelitEstVel\MolestiaeOfficiisUt\RationeLiberoEnum', [
            'DoloremCommodi\VelitEstVel\MolestiaeOfficiisUt\RationeLiberoEnum -[declaration-enum-case]-> RationeTotam\CupiditateEnimQuia\RationeNihilVel\VoluptatemEtEnum::POSSIMUS_QUAE_EARUM_CASE',
            'DoloremCommodi\VelitEstVel\MolestiaeOfficiisUt\RationeLiberoEnum -[declaration-enum-case]-> SimiliqueEarumUt\MaximeEtSed\SitAutEnum::AUT_FACERE_CASE',
            'DoloremCommodi\VelitEstVel\MolestiaeOfficiisUt\RationeLiberoEnum -[declaration-enum-case]-> SitEaque\ExplicaboDoloremqueVero\VeniamVoluptasEius\SimiliqueRationeNonEnum::POSSIMUS_EA_CASE',
        ]];

        yield 'constantGraph' => ['constantGraph', 'VoluptatumDoloremCommodi\VelitEstVelClass::MOLESTIAE_OFFICIIS_UT_CONST', []];

        yield 'enumCaseGraph' => ['enumCaseGraph', 'VoluptatumDoloremCommodi\VelitEstVelEnum::MOLESTIAE_OFFICIIS_UT_CASE', []];

        yield 'builtinGraph' => ['builtinGraph', 'VoluptatumDoloremCommodi\VelitEstVel', []];
    }
}
