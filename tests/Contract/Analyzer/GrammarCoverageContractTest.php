<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\ClosureUse;
use PhpParser\Node\Const_;
use PhpParser\Node\DeclareItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp\BitwiseAnd;
use PhpParser\Node\Expr\AssignOp\BitwiseOr;
use PhpParser\Node\Expr\AssignOp\BitwiseXor;
use PhpParser\Node\Expr\AssignOp\Coalesce;
use PhpParser\Node\Expr\AssignOp\Concat;
use PhpParser\Node\Expr\AssignOp\Div;
use PhpParser\Node\Expr\AssignOp\Minus;
use PhpParser\Node\Expr\AssignOp\Mod;
use PhpParser\Node\Expr\AssignOp\Mul;
use PhpParser\Node\Expr\AssignOp\Plus;
use PhpParser\Node\Expr\AssignOp\Pow;
use PhpParser\Node\Expr\AssignOp\ShiftLeft;
use PhpParser\Node\Expr\AssignOp\ShiftRight;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\BinaryOp\GreaterOrEqual;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\LogicalAnd;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Expr\BinaryOp\LogicalXor;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BinaryOp\Pipe;
use PhpParser\Node\Expr\BinaryOp\Smaller;
use PhpParser\Node\Expr\BinaryOp\SmallerOrEqual;
use PhpParser\Node\Expr\BinaryOp\Spaceship;
use PhpParser\Node\Expr\BitwiseNot;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast\Bool_;
use PhpParser\Node\Expr\Cast\Double;
use PhpParser\Node\Expr\Cast\Object_;
use PhpParser\Node\Expr\Cast\Void_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Clone_;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\Error;
use PhpParser\Node\Expr\ErrorSuppress;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\ShellExec;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Expr\YieldFrom;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Name\Relative;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\EncapsedStringPart;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\MagicConst\File;
use PhpParser\Node\Scalar\MagicConst\Line;
use PhpParser\Node\Scalar\MagicConst\Method;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\DeclareDeclare;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Finally_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\Goto_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\HaltCompiler;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Label;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TraitUseAdaptation\Alias;
use PhpParser\Node\Stmt\TraitUseAdaptation\Precedence;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\Stmt\While_;
use PhpParser\Node\UnionType;
use PhpParser\Node\UseItem;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\Node\VarLikeIdentifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class GrammarCoverageContractTest extends TestCase
{
    /**
     * PhpParser node types that create dependency edges in peq.
     *
     * Keys are FQCNs; values describe what peq should detect.
     * Includes both currently handled and known-gap entries.
     */
    private const DEPENDENCY_PRODUCING = [
        // Currently handled by DependencyCollector processors
        Class_::class => 'Class declaration (ClassLikeProcessor)',
        Interface_::class => 'Interface declaration (ClassLikeProcessor)',
        Trait_::class => 'Trait declaration (ClassLikeProcessor)',
        Enum_::class => 'Enum declaration (ClassLikeProcessor)',
        ClassMethod::class => 'Method declaration (FunctionLikeProcessor)',
        Function_::class => 'Function declaration (FunctionLikeProcessor)',
        Property::class => 'Property declaration (PropertyProcessor)',
        ClassConst::class => 'Class constant declaration (ClassConstProcessor)',
        EnumCase::class => 'Enum case declaration (EnumCaseProcessor)',
        Param::class => 'Promoted property (PromotedPropertyProcessor)',
        New_::class => 'Instantiation (InstantiationProcessor)',
        StaticCall::class => 'Static method call (StaticCallProcessor)',
        ClassConstFetch::class => 'Class constant fetch (ConstFetchProcessor)',
        Instanceof_::class => 'Instanceof check (InstanceofProcessor)',
        Catch_::class => 'Catch clause (CatchProcessor)',

        // Usage processors (via InClassMethodNodeProcessor + DependencyCollector)
        FuncCall::class => 'Function call (FunctionCallProcessor)',
        MethodCall::class => 'Instance method call (MethodCallProcessor)',
        PropertyFetch::class => 'Property access (PropertyAccessProcessor)',
        StaticPropertyFetch::class => 'Static property access (StaticPropertyAccessProcessor)',
        NullsafeMethodCall::class => 'Nullsafe method call (MethodCallProcessor)',
        NullsafePropertyFetch::class => 'Nullsafe property fetch (PropertyAccessProcessor)',

        // Type nodes that should produce edges
        UnionType::class => 'Union type declaration',
        IntersectionType::class => 'Intersection type declaration',
        NullableType::class => 'Nullable type declaration',
    ];

    /**
     * PhpParser node types that do NOT create dependency edges.
     *
     * Every concrete PhpParser node type must appear in exactly one of
     * DEPENDENCY_PRODUCING or NOT_DEPENDENCY_PRODUCING.
     */
    private const NOT_DEPENDENCY_PRODUCING = [
        Arg::class,
        ArrayItem::class,
        Attribute::class,
        AttributeGroup::class,
        ClosureUse::class,
        Const_::class,
        DeclareItem::class,
        ArrayDimFetch::class,
        Expr\ArrayItem::class,
        Array_::class,
        ArrowFunction::class,
        Assign::class,
        BitwiseAnd::class,
        BitwiseOr::class,
        BitwiseXor::class,
        Coalesce::class,
        Concat::class,
        Div::class,
        Minus::class,
        Mod::class,
        Mul::class,
        Plus::class,
        Pow::class,
        ShiftLeft::class,
        ShiftRight::class,
        AssignRef::class,
        Expr\BinaryOp\BitwiseAnd::class,
        Expr\BinaryOp\BitwiseOr::class,
        Expr\BinaryOp\BitwiseXor::class,
        BooleanAnd::class,
        BooleanOr::class,
        Expr\BinaryOp\Coalesce::class,
        Expr\BinaryOp\Concat::class,
        Expr\BinaryOp\Div::class,
        Equal::class,
        Greater::class,
        GreaterOrEqual::class,
        Identical::class,
        LogicalAnd::class,
        LogicalOr::class,
        LogicalXor::class,
        Expr\BinaryOp\Minus::class,
        Expr\BinaryOp\Mod::class,
        Expr\BinaryOp\Mul::class,
        NotEqual::class,
        NotIdentical::class,
        Expr\BinaryOp\Plus::class,
        Expr\BinaryOp\Pow::class,
        Expr\BinaryOp\ShiftLeft::class,
        Expr\BinaryOp\ShiftRight::class,
        Smaller::class,
        SmallerOrEqual::class,
        Spaceship::class,
        Pipe::class,
        BitwiseNot::class,
        BooleanNot::class,
        Expr\Cast\Array_::class,
        Bool_::class,
        Double::class,
        Expr\Cast\Int_::class,
        Object_::class,
        Expr\Cast\String_::class,
        Expr\Cast\Unset_::class,
        Void_::class,
        Clone_::class,
        Closure::class,
        Expr\ClosureUse::class,
        ConstFetch::class,
        Empty_::class,
        Error::class,
        ErrorSuppress::class,
        Eval_::class,
        Exit_::class,
        Include_::class,
        Isset_::class,
        List_::class,
        Match_::class,
        PostDec::class,
        PostInc::class,
        PreDec::class,
        PreInc::class,
        Print_::class,
        ShellExec::class,
        Ternary::class,
        Throw_::class,
        UnaryMinus::class,
        UnaryPlus::class,
        Variable::class,
        YieldFrom::class,
        Yield_::class,
        Identifier::class,
        InterpolatedStringPart::class,
        MatchArm::class,
        Name::class,
        FullyQualified::class,
        Relative::class,
        PropertyHook::class,
        PropertyItem::class,
        DNumber::class,
        Encapsed::class,
        EncapsedStringPart::class,
        Float_::class,
        Int_::class,
        InterpolatedString::class,
        LNumber::class,
        Node\Scalar\MagicConst\Class_::class,
        Dir::class,
        File::class,
        Node\Scalar\MagicConst\Function_::class,
        Line::class,
        Method::class,
        Node\Scalar\MagicConst\Namespace_::class,
        Node\Scalar\MagicConst\Property::class,
        Node\Scalar\MagicConst\Trait_::class,
        String_::class,
        StaticVar::class,
        Block::class,
        Break_::class,
        Case_::class,
        Node\Stmt\Const_::class,
        Continue_::class,
        DeclareDeclare::class,
        Declare_::class,
        Do_::class,
        Echo_::class,
        ElseIf_::class,
        Else_::class,
        Expression::class,
        Finally_::class,
        For_::class,
        Foreach_::class,
        Global_::class,
        Goto_::class,
        GroupUse::class,
        HaltCompiler::class,
        If_::class,
        InlineHTML::class,
        Label::class,
        Namespace_::class,
        Nop::class,
        PropertyProperty::class,
        Return_::class,
        Node\Stmt\StaticVar::class,
        Static_::class,
        Switch_::class,
        TraitUse::class,
        Alias::class,
        Precedence::class,
        TryCatch::class,
        Unset_::class,
        UseUse::class,
        Use_::class,
        While_::class,
        UseItem::class,
        VarLikeIdentifier::class,
        VariadicPlaceholder::class,
    ];

    #[Test]
    public function testAllPhpParserNodeTypesAreClassified(): void
    {
        $discovered = self::discoverPhpParserNodeTypes();
        $dependencyProducing = array_keys(self::DEPENDENCY_PRODUCING);
        $notDependencyProducing = self::NOT_DEPENDENCY_PRODUCING;
        $classified = array_merge($dependencyProducing, $notDependencyProducing);

        $unclassified = array_diff($discovered, $classified);
        $extra = array_diff($classified, $discovered);

        self::assertSame(
            [],
            array_values($unclassified),
            'The following PhpParser node types are not classified in either DEPENDENCY_PRODUCING or NOT_DEPENDENCY_PRODUCING: '
            .implode(', ', $unclassified),
        );

        self::assertSame(
            [],
            array_values($extra),
            'The following classified types were not discovered as concrete PhpParser node types: '
            .implode(', ', $extra),
        );
    }

    #[Test]
    public function testDependencyProducingClassesExist(): void
    {
        foreach (array_keys(self::DEPENDENCY_PRODUCING) as $fqcn) {
            self::assertTrue(
                class_exists($fqcn),
                "DEPENDENCY_PRODUCING class does not exist: {$fqcn}",
            );
        }
    }

    #[Test]
    public function testNotDependencyProducingClassesExist(): void
    {
        foreach (self::NOT_DEPENDENCY_PRODUCING as $fqcn) {
            self::assertTrue(
                class_exists($fqcn),
                "NOT_DEPENDENCY_PRODUCING class does not exist: {$fqcn}",
            );
        }
    }

    #[Test]
    public function testNoOverlapBetweenClassifications(): void
    {
        $dependencyProducing = array_keys(self::DEPENDENCY_PRODUCING);
        $notDependencyProducing = self::NOT_DEPENDENCY_PRODUCING;
        $overlap = array_intersect($dependencyProducing, $notDependencyProducing);

        self::assertSame(
            [],
            array_values($overlap),
            'The following types appear in both DEPENDENCY_PRODUCING and NOT_DEPENDENCY_PRODUCING: '
            .implode(', ', $overlap),
        );
    }

    /**
     * @return list<class-string<Node>>
     */
    private static function discoverPhpParserNodeTypes(): array
    {
        $fileName = (new \ReflectionClass(Expr::class))->getFileName();
        assert($fileName !== false);
        $nodeDir = dirname($fileName);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($nodeDir, \FilesystemIterator::SKIP_DOTS)
        );
        $types = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace('/', '\\', substr($file->getPathname(), strlen($nodeDir) + 1, -4));
            $fqcn = 'PhpParser\Node\\'.$relative;
            if (!class_exists($fqcn)) {
                continue;
            }
            $rc = new \ReflectionClass($fqcn);
            if (!$rc->isAbstract() && !$rc->isInterface() && $rc->implementsInterface(Node::class)) {
                /** @var class-string<Node> $fqcn */
                $types[] = $fqcn;
            }
        }
        sort($types);

        return $types;
    }
}
