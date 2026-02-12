<?php

declare(strict_types = 1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

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
  ])
  ->withRules([
    SafeDeclareStrictTypesRector::class,
  ])
  ->withSets([
      SetList::CODE_QUALITY,
      SetList::DEAD_CODE,
      //SetList::TYPE_DECLARATION,
      SetList::EARLY_RETURN,
  ])
  ->withPhpVersion(PhpVersion::PHP_82)
  ->withPhpSets(php82: true);
