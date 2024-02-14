<?php

return [
	Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => [ 'all' => true ],
	Wikimedia\ToolforgeBundle\ToolforgeBundle::class => [ 'all' => true ],
	Symfony\Bundle\MakerBundle\MakerBundle::class => [ 'dev' => true ],
	Symfony\Bundle\TwigBundle\TwigBundle::class => [ 'all' => true ],
	Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => [ 'dev' => true, 'test' => true ],
	Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => [ 'all' => true ],
	Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => [ 'all' => true ],
	Symfony\Bundle\MonologBundle\MonologBundle::class => [ 'all' => true ],
	Nelmio\CorsBundle\NelmioCorsBundle::class => [ 'all' => true ],
	Nelmio\ApiDocBundle\NelmioApiDocBundle::class => [ 'all' => true ],
];
