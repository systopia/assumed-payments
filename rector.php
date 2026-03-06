<?php

declare(strict_types = 1);

use Rector\CodeQuality\Rector\Include_\AbsolutizeRequireAndIncludePathRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
  ->withPaths([
    __DIR__ . '/api',
    __DIR__ . '/Civi',
    __DIR__ . '/CRM',
    __DIR__ . '/assumed_payments.php',
    __DIR__ . '/tests',
  ])
  ->withSkip([
    ClassPropertyAssignToConstructorPromotionRector::class => NULL,
    AddVoidReturnTypeWhereNoReturnRector::class => NULL,
    RemoveUselessParamTagRector::class => NULL,
    RemoveUselessReturnTagRector::class => NULL,
    AbsolutizeRequireAndIncludePathRector::class => [
      __DIR__ . '/assumed_payments.php',
    ],
  ])
  ->withRules([
    SafeDeclareStrictTypesRector::class,
  ])
  ->withSets([
    SetList::CODE_QUALITY,
    SetList::DEAD_CODE,
    SetList::TYPE_DECLARATION,
    SetList::EARLY_RETURN,
  ]);
