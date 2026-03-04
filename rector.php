<?php

declare(strict_types = 1);

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
    ClassPropertyAssignToConstructorPromotionRector::class,
    AddVoidReturnTypeWhereNoReturnRector::class,
    RemoveUselessParamTagRector::class,
    RemoveUselessReturnTagRector::class,
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
