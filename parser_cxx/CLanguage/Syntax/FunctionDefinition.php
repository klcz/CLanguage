<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\CompiledFunction;
use CLanguage\Types\CFunctionType;

class FunctionDefinition extends Statement
{
    public DeclarationSpecifiers $Specifiers;
    public Declarator $Declarator;
    /** @--var Declaration[]|null */
    public ?array $ParameterDeclarations;
    public Block $Body;

    public function __construct(
        DeclarationSpecifiers $specifiers,
        Declarator            $declarator,
        ?array                $parameterDeclarations,
        Block                 $body
    )
    {
        $this->Specifiers = $specifiers;
        $this->Declarator = $declarator;
        $this->ParameterDeclarations = $parameterDeclarations;
        $this->Body = $body;
    }

    public function alwaysReturns(): bool
    {
        return false;
    }

    public function addDeclarationToBlock(BlockContext $context): void
    {
        $block = $context->Block;
        $ftype = $context->makeCType($this->Specifiers, $this->Declarator, null, $block);
        if ($ftype instanceof CFunctionType) {
            $name = $this->Declarator->DeclaredIdentifier;
            $inner = $this->Declarator->InnerDeclarator;
            $nameContext = ($inner instanceof IdentifierDeclarator) ? implode('::', $inner->Context) : '';
            $f = new CompiledFunction($name, $nameContext, $ftype, $this->Body);
            $block->Functions[] = $f;
        }
    }

    protected function doEmit(EmitContext $ec): void
    {
        // Emitted by the compiler
    }
}
