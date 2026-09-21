<?php

declare(strict_types=1);

namespace Spec\Context;

use App\Gql\Element\GraphSchema;
use App\Gql\GqlException;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\FunctionCatalog;
use App\Gql\Parsing\Parser;
use App\Gql\ReservedWords;
use App\Gql\StatusCode;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use PHPUnit\Framework\Assert;

/**
 * Steps that state the conformance claim itself, against ISO's own enumerations.
 *
 * Clause 24 of ISO/IEC 39075 does not let an implementation say "it conforms" and stop.
 * A claim names the class of conformance and then, under 24.3, the optional features
 * implemented; 24.5.3 requires any extension to be stated too. That makes the claim a
 * register, and a register can be checked: the standard publishes the feature codes and
 * the condition codes as artifacts, so every line can be looked up rather than believed.
 *
 * The check that matters most is the last one. A register listing features nothing
 * exercises would be a longer way of asserting conformance; requiring every claimed
 * feature to be stated by a scenario of this suite is what makes the claim cost
 * something to make.
 */
final class ConformanceContext implements Context
{
    /**
     * The register the scenario gave, by feature code.
     *
     * @var array<string, array{claimed: bool, description: string}>
     */
    private array $register = [];

    /**
     * The implementation-defined items the scenario gave, by code.
     *
     * @var array<string, string>
     */
    private array $defined = [];

    /**
     * Takes the conformance register of the scenario.
     *
     * @param TableNode $register A row per optional feature the standard defines
     */
    #[Given('the conformance register:')]
    public function theConformanceRegister(TableNode $register): void
    {
        $this->register = [];
        foreach ($register->getHash() as $row) {
            $this->register[trim($row['feature'] ?? '')] = [
                'claimed' => trim($row['claimed'] ?? '') === 'yes',
                'description' => trim($row['description'] ?? ''),
            ];
        }
    }

    /**
     * States that every feature code in the register is one the standard defines.
     */
    #[Then('every feature code is one ISO\/IEC 39075 defines')]
    public function everyFeatureCodeIsOneTheStandardDefines(): void
    {
        $invented = array_diff(array_keys($this->register), array_keys(IsoFeatures::all()));

        Assert::assertSame(
            [],
            array_values($invented),
            'The register names features ISO/IEC 39075 does not define.',
        );
    }

    /**
     * States that the register describes each feature the way the standard describes it.
     */
    #[Then('every feature is described the way the standard describes it')]
    public function everyFeatureIsDescribedTheWayTheStandardDescribesIt(): void
    {
        $defined = IsoFeatures::all();
        foreach ($this->register as $code => $entry) {
            Assert::assertSame(
                $defined[$code] ?? null,
                $entry['description'],
                sprintf('The register describes feature "%s" differently from the standard.', $code),
            );
        }
    }

    /**
     * States that the register answers for every optional feature the standard defines.
     */
    #[Then('the register answers for every optional feature the standard defines')]
    public function theRegisterAnswersForEveryOptionalFeature(): void
    {
        $missing = array_diff(array_keys(IsoFeatures::all()), array_keys($this->register));

        Assert::assertSame(
            [],
            array_values($missing),
            sprintf('The register says nothing about %d of the features the standard defines.', count($missing)),
        );
    }

    /**
     * States that every feature the register claims is exercised somewhere in this suite.
     */
    #[Then('every feature the register claims is stated by a scenario of this suite')]
    public function everyClaimedFeatureIsStatedByAScenario(): void
    {
        $stated = SuiteTags::featureCodes();
        $unstated = [];
        foreach ($this->register as $code => $entry) {
            if ($entry['claimed'] && !in_array($code, $stated, true)) {
                $unstated[] = $code;
            }
        }

        Assert::assertSame(
            [],
            $unstated,
            'The register claims features that no scenario of this suite states. '
            .'A claim nothing exercises is a claim nobody checked.',
        );
    }

    /**
     * States that no scenario claims a feature the register does not.
     */
    #[Then('no scenario states a feature the register does not claim')]
    public function noScenarioStatesAnUnclaimedFeature(): void
    {
        $claimed = [];
        foreach ($this->register as $code => $entry) {
            if ($entry['claimed']) {
                $claimed[] = $code;
            }
        }

        Assert::assertSame(
            [],
            array_values(array_diff(SuiteTags::featureCodes(), $claimed)),
            'Scenarios state features the register does not claim.',
        );
    }

    /**
     * Takes the register of what the implementation defines where the standard lets it.
     *
     * @param TableNode $register A row per item this implementation has had to settle
     */
    #[Given('the implementation-defined register:')]
    public function theImplementationDefinedRegister(TableNode $register): void
    {
        $this->defined = [];
        foreach ($register->getHash() as $row) {
            $this->defined[trim($row['item'] ?? '')] = trim($row['description'] ?? '');
        }
    }

    /**
     * States that everything the register settles is something the standard left open.
     *
     * Clause 24.5 asks an implementation to define every implementation-defined item
     * rather than to decide freely: `--hops` refusing `{1,20}` looks like peq declining
     * a query GQL allows until IL018 says the largest upper bound a quantifier may be
     * written with is the implementation's to choose. So the register names the code,
     * and the code is looked up.
     */
    #[Then('every item the register settles is one ISO\/IEC 39075 leaves to an implementation')]
    public function everyItemSettledIsOneTheStandardLeavesOpen(): void
    {
        foreach ($this->defined as $code => $description) {
            Assert::assertSame(
                IsoImplementationDefined::itemOf($code),
                $description,
                sprintf('The register settles "%s", which is not an item ISO/IEC 39075 words that way.', $code),
            );
        }
    }

    /**
     * States that the implementation reserves exactly the words the standard reserves.
     *
     * This is the one claim about GQL's syntax that cannot be made by running queries.
     * Reserving too few words means accepting programs GQL does not define, and a
     * reader only finds out when another implementation refuses one of them; reserving
     * too many means refusing names the standard allows. Either way the difference is
     * a list, so the list is compared.
     */
    #[Then('the words the implementation reserves are the words ISO\/IEC 39075 reserves')]
    public function theReservedWordsAreTheStandardsReservedWords(): void
    {
        Assert::assertSame(
            IsoGrammar::reservedWords(),
            ReservedWords::WORDS,
            'The words the implementation reserves are not <reserved word> and <pre-reserved word> '
            .'as ISO/IEC 39075 writes them.',
        );
    }

    /**
     * States that every word the implementation spells is a word the grammar writes.
     *
     * A word of an implementation's own is the cheapest extension there is to make and
     * the most expensive to have made: `CONTAINS` is useful, reads well, and quietly
     * turns the language into a dialect that has to be explained. So every upper-case
     * word written as a literal anywhere in the engine is looked up in ISO's grammar
     * artifact, which is where the answer to "is that a GQL word?" actually lives.
     */
    #[Then('every word the implementation spells is one ISO\/IEC 39075\'s grammar writes')]
    public function everyWordTheImplementationSpellsIsOneTheGrammarWrites(): void
    {
        $written = IsoGrammar::keywords();
        $invented = [];
        foreach (EngineVocabulary::words() as $word => $found) {
            if (!in_array($word, $written, true)) {
                $invented[] = $word.' ('.implode(', ', $found[1]).')';
            }
        }

        Assert::assertSame(
            [],
            $invented,
            'The implementation spells words ISO/IEC 39075 does not write. '
            .'A word of its own makes the query language a dialect rather than the one its readers know.',
        );
    }

    /**
     * States that the artifacts everything here is checked against are the ones published.
     *
     * Every other step reads an artifact and believes it, which is worth something only
     * while the artifacts are the ones ISO published. An artifact edited until it agreed
     * with the implementation would make this whole suite pass and say nothing.
     */
    #[Then('every artifact this specification reads is the one it arrived as')]
    public function everyArtifactIsTheOneItArrivedAs(): void
    {
        $listed = IsoArtifacts::manifest();

        Assert::assertNotSame([], $listed, 'spec/iso/artifacts.txt lists no artifact to be checked.');
        foreach ($listed as $name => $artifact) {
            IsoArtifacts::path($name);
            Assert::assertSame(
                $artifact['sum'],
                IsoArtifacts::sumOf($name),
                sprintf('%s is not the file ISO publishes at %s.', $name, $artifact['url']),
            );
        }
    }

    /**
     * States that every item the register settles is shown settled that way by a scenario.
     *
     * An item settled in a table and nowhere else is a sentence; an item a scenario
     * cites is behaviour somebody can watch. So the register is compared with the
     * implementation-defined items the scenarios of this suite cite, both ways.
     */
    #[Then('every item the register settles is stated by a scenario of this suite')]
    public function everyItemSettledIsStatedByAScenario(): void
    {
        $unstated = array_values(array_diff(array_keys($this->defined), SuiteSources::cited('implementation-defined.xml')));

        Assert::assertSame([], $unstated, 'The register settles items no scenario of this suite shows settled.');
    }

    /**
     * States that no scenario shows an item settled that the register does not settle.
     */
    #[Then('no scenario states an item the register does not settle')]
    public function noScenarioStatesAnItemTheRegisterDoesNotSettle(): void
    {
        $unsettled = array_values(array_diff(SuiteSources::cited('implementation-defined.xml'), array_keys($this->defined)));

        Assert::assertSame([], $unsettled, 'Scenarios cite implementation-defined items the register does not settle.');
    }

    /**
     * States that every scenario says where what it states is published.
     *
     * A scenario nobody can trace to a published text is a rule somebody made up, and
     * the way to find one is to ask each scenario for its source and look the source
     * up: a subclause in the standard's table of contents, a production in its grammar,
     * a code in its artifacts, a section of the editors' paper.
     */
    #[Then('every scenario cites where what it states is published')]
    public function everyScenarioCitesWhereWhatItStatesIsPublished(): void
    {
        $faults = [];
        foreach (SuiteSources::scenarios() as $scenario) {
            foreach (SuiteSources::faults($scenario) as $fault) {
                $faults[] = sprintf('%s "%s" %s', $scenario['where'], $scenario['title'], $fault);
            }
        }

        Assert::assertSame([], $faults, 'Scenarios state something without citing where it is published.');
    }

    /**
     * States that every subclause a scenario points at is one the standard numbers.
     *
     * A scenario claims to state a subclause of ISO/IEC 39075, and a number is the
     * easiest thing in a specification to get wrong: nothing complains when a scenario
     * about string expressions is filed under the subclause that defines casts. So the
     * numbers are looked up.
     */
    #[Then('every subclause a scenario states is one ISO\/IEC 39075 numbers')]
    public function everySubclauseStatedIsOneTheStandardNumbers(): void
    {
        $invented = [];
        foreach (SuiteTags::subclauses() as $number) {
            if (!IsoSubclauses::numbers($number)) {
                $invented[] = $number;
            }
        }

        Assert::assertSame(
            [],
            $invented,
            'Scenarios point at subclauses ISO/IEC 39075 does not number.',
        );
    }

    /**
     * States that every function the implementation offers is one GQL calls.
     *
     * A word being in the grammar does not make it a function: `LABELS` is in there,
     * in the syntax for declaring a graph type, and `labels(x)` is still not GQL. So
     * each name is looked up among the words the grammar actually writes a left
     * parenthesis after, which is the only reading of "GQL has this function" that
     * does not come down to somebody's memory.
     */
    #[Then('every function the implementation offers is one ISO\/IEC 39075\'s grammar calls')]
    public function everyFunctionOfferedIsOneTheGrammarCalls(): void
    {
        $called = IsoGrammar::calledNames();
        $invented = [];
        foreach ([...FunctionCatalog::all(), ...AggregateCatalog::all()] as $name) {
            if (!in_array(strtoupper($name), $called, true)) {
                $invented[] = $name;
            }
        }

        Assert::assertSame(
            [],
            $invented,
            'The implementation offers functions ISO/IEC 39075 does not call. '
            .'A useful function of our own is still a dialect a reader has to be told about.',
        );
    }

    /**
     * States that every name the schema reports back is a name a query can write.
     *
     * A graph is named by whoever wrote the code it was read from, so some of its
     * labels and properties spell words GQL reserves. `--schema` is the only thing an
     * agent has to write a query from, which makes a name it reports that no query can
     * write a worse answer than no answer at all.
     */
    #[Then('every name the schema offers is one a query can write')]
    public function everyNameTheSchemaOffersIsOneAQueryCanWrite(): void
    {
        foreach (self::schemaNames() as $category => $names) {
            foreach ($names as $name) {
                $program = str_contains($category, 'label')
                    ? sprintf('MATCH (x:%s) RETURN 1 AS n', $name)
                    : sprintf('MATCH (x) RETURN x.%s AS n', $name);

                Assert::assertTrue(
                    self::reads($program),
                    sprintf('The schema offers the %s "%s", which no query can write.', $category, $name),
                );
            }
        }
    }

    /**
     * Returns the labels and properties the schema offers, by category.
     *
     * @return array<string, list<string>> The names, by category
     */
    private static function schemaNames(): array
    {
        $names = [];
        foreach (GraphSchema::table()->rows as $row) {
            $category = $row->values[0]->toText();
            if (!str_contains($category, 'label') && !str_contains($category, 'property')) {
                continue;
            }
            $names[$category][] = $row->values[1]->toText();
        }

        return $names;
    }

    /**
     * Reports whether a GQL-program is one the implementation reads.
     *
     * @param string $program The program
     *
     * @return bool True when it reads
     */
    private static function reads(string $program): bool
    {
        try {
            Parser::read($program);
        } catch (GqlException) {
            return false;
        }

        return true;
    }

    /**
     * States that every GQLSTATUS the implementation can report is one the standard defines.
     */
    #[Then('every GQLSTATUS the implementation reports is one ISO\/IEC 39075 defines')]
    public function everyStatusIsOneTheStandardDefines(): void
    {
        foreach (StatusCode::cases() as $status) {
            Assert::assertTrue(
                IsoConditions::defines($status->value),
                sprintf('The implementation reports GQLSTATUS %s, which the standard does not define.', $status->value),
            );
        }
    }

    /**
     * States that every GQLSTATUS is reported in the wording the standard gives it.
     */
    #[Then('every GQLSTATUS carries the condition the standard words for it')]
    public function everyStatusCarriesTheStandardsWording(): void
    {
        foreach (StatusCode::cases() as $status) {
            Assert::assertSame(
                IsoConditions::conditionOf($status->value),
                $status->condition(),
                sprintf('The implementation words GQLSTATUS %s differently from the standard.', $status->value),
            );
        }
    }
}
