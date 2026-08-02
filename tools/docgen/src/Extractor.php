<?php

namespace Yarunoka\Docgen;

use LogicException;
use PhpParser\Comment\Doc;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

/**
 * Extracts class-like declarations from one PHP source file by walking its
 * AST. Pure syntax work: the target file is never autoloaded or executed.
 */
final class Extractor extends NodeVisitorAbstract
{
    /** @var list<ClassDoc> */
    private array $declarations = [];

    /** @return list<ClassDoc> */
    public function extract(string $file): array
    {
        $source = file_get_contents($file);
        if ($source === false) {
            throw new LogicException("cannot read $file");
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver()); // resolves namespacedName on declarations
        $traverser->addVisitor($this);

        $this->declarations = [];
        $traverser->traverse($parser->parse($source) ?? []);

        return $this->declarations;
    }

    public function enterNode(Node $node): null
    {
        // Anonymous classes are expressions, not declarations
        if ($node instanceof Node\Stmt\ClassLike
            && !($node instanceof Node\Stmt\Class_ && $node->isAnonymous())) {
            $this->declarations[] = $this->classDoc($node);
        }

        return null;
    }

    private function classDoc(Node\Stmt\ClassLike $node): ClassDoc
    {
        $extends = null;
        $implements = [];
        $backingType = null;
        if ($node instanceof Node\Stmt\Class_) {
            $extends = $node->extends?->toString();
            $implements = array_values(array_map(fn(Node\Name $n) => $n->toString(), $node->implements));
        }
        if ($node instanceof Node\Stmt\Interface_) {
            // Interfaces have no implements clause; their parents arrive via extends (plural)
            $extends = implode(', ', array_map(fn(Node\Name $n) => $n->toString(), $node->extends)) ?: null;
        }
        if ($node instanceof Node\Stmt\Enum_) {
            $implements = array_values(array_map(fn(Node\Name $n) => $n->toString(), $node->implements));
            $backingType = $this->type($node->scalarType);
        }

        $constants = [];
        $properties = [];
        $methods = [];
        $cases = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassConst) {
                foreach ($stmt->consts as $const) {
                    $constants[] = new ConstantDoc(
                        $const->name->toString(),
                        $this->visibility($stmt->flags),
                        $this->docblock($stmt->getDocComment()),
                    );
                }
            }
            if ($stmt instanceof Node\Stmt\EnumCase) {
                $cases[] = $stmt->name->toString();
            }
            if ($stmt instanceof Node\Stmt\Property) {
                foreach ($stmt->props as $prop) {
                    $properties[] = new PropertyDoc(
                        $prop->name->toString(),
                        $this->visibility($stmt->flags),
                        $this->type($stmt->type),
                        $stmt->isStatic(),
                        $stmt->isReadonly(),
                        false,
                        $this->docblock($stmt->getDocComment()),
                    );
                }
            }
            if ($stmt instanceof Node\Stmt\ClassMethod) {
                $methods[] = $this->methodDoc($stmt, $properties);
            }
        }

        $fqcn = $node->namespacedName?->toString() ?? $node->name?->toString() ?? '';
        $shortName = $node->name?->toString() ?? '';

        return new ClassDoc(
            match (true) {
                $node instanceof Node\Stmt\Interface_ => 'interface',
                $node instanceof Node\Stmt\Enum_ => 'enum',
                $node instanceof Node\Stmt\Trait_ => 'trait',
                default => 'class',
            },
            $shortName,
            $fqcn,
            str_contains($fqcn, '\\') ? substr($fqcn, 0, (int) strrpos($fqcn, '\\')) : '',
            $node instanceof Node\Stmt\Class_ && $node->isFinal(),
            $node instanceof Node\Stmt\Class_ && $node->isAbstract(),
            $node instanceof Node\Stmt\Class_ && $node->isReadonly(),
            $extends,
            $implements,
            $backingType,
            $cases,
            $this->docblock($node->getDocComment()),
            $constants,
            $properties,
            $methods,
        );
    }

    /** @param list<PropertyDoc> $properties promoted parameters are appended here */
    private function methodDoc(Node\Stmt\ClassMethod $stmt, array &$properties): MethodDoc
    {
        $rendered = [];
        foreach ($stmt->params as $param) {
            $name = '$' . $this->paramName($param);
            $type = $this->type($param->type);
            $rendered[] = $type !== null ? "$type $name" : $name;

            if ($param->isPromoted()) {
                $properties[] = new PropertyDoc(
                    $this->paramName($param),
                    $this->visibility($param->flags),
                    $type,
                    false,
                    $param->isReadonly(),
                    true,
                    $this->docblock($param->getDocComment()),
                );
            }
        }
        $returnType = $this->type($stmt->returnType);

        return new MethodDoc(
            $stmt->name->toString(),
            $this->visibility($stmt->flags),
            $stmt->isStatic(),
            $stmt->isAbstract(),
            sprintf(
                '%s(%s)%s',
                $stmt->name->toString(),
                implode(', ', $rendered),
                $returnType !== null ? ": $returnType" : '',
            ),
            $this->docblock($stmt->getDocComment()),
        );
    }

    private function docblock(?Doc $doc): ?Docblock
    {
        return $doc !== null ? Docblock::parse($doc->getText()) : null;
    }

    private function type(?Node $type): ?string
    {
        // Only Name and Identifier stringify directly; composite types fold recursively
        return match (true) {
            $type === null => null,
            $type instanceof Node\NullableType => '?' . $this->type($type->type),
            $type instanceof Node\UnionType => implode('|', array_map($this->type(...), $type->types)),
            $type instanceof Node\IntersectionType => implode('&', array_map($this->type(...), $type->types)),
            $type instanceof Node\Name, $type instanceof Node\Identifier => $type->toString(),
            default => throw new LogicException('unsupported type node: ' . $type::class),
        };
    }

    private function paramName(Node\Param $param): string
    {
        $var = $param->var;
        if ($var instanceof Node\Expr\Variable && is_string($var->name)) {
            return $var->name;
        }

        throw new LogicException('unsupported parameter variable');
    }

    private function visibility(int $flags): string
    {
        return match (true) {
            (bool) ($flags & Modifiers::PRIVATE) => 'private',
            (bool) ($flags & Modifiers::PROTECTED) => 'protected',
            default => 'public',
        };
    }
}
