<?php
declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(['src', 'tools', 'public', 'tests'])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules(['@PER-CS2.0' => true])
    ->setFinder($finder);
