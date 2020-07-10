<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftunit\helpers;

use Codeception\Test\Unit;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\services\ProjectConfig;

class ProjectConfigHelperTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @dataProvider packedUnpackedDataProvider
     *
     * @param array $field
     * @param array $expectedResult
     */
    public function testAssociativeArrayConfigTransforms($unpackedData, $packedData)
    {
        $this->assertSame($packedData, ProjectConfigHelper::packAssociativeArrays($unpackedData));
        $this->assertSame($unpackedData, ProjectConfigHelper::unpackAssociativeArrays($packedData));
    }

    /**
     * @dataProvider cleanupConfigDataProvider
     * @param $inputData
     * @param $expectedResult
     */
    public function testCleanupConfig($inputData, $expectedResult)
    {
        $this->assertSame($expectedResult, ProjectConfigHelper::cleanupConfig($inputData));
    }

    /**
     * @dataProvider splitIntoComponentsProvider
     * @param $inputData
     * @param $expectedResult
     */
    public function testSplitIntoComponents($inputData, $expectedResult)
    {
        $this->assertSame($expectedResult, ProjectConfigHelper::splitConfigIntoComponents($inputData));
    }

    /**
     * @return array
     */
    public function packedUnpackedDataProvider(): array
    {
        return [
            [
                [
                    'plainSettings' => 'plain',
                    'associativeSettings' => [
                        'some' => 'thing',
                        'foo' => ['bar', 'baz']
                    ],
                    'randomArray' => [1, 7, 2, 'ok']
                ],
                [
                    'plainSettings' => 'plain',
                    'associativeSettings' => [
                        ProjectConfig::CONFIG_ASSOC_KEY => [
                            ['some', 'thing'],
                            ['foo', ['bar', 'baz']]
                        ]
                    ],
                    'randomArray' => [1, 7, 2, 'ok']
                ]
            ],
            [
                [
                    'test' => [
                        'rootA' => [
                            'label' => 'childA'
                        ],
                        'rootB' => [
                            'label' => 'childB'
                        ]
                    ]
                ],
                [
                    'test' => [
                        ProjectConfig::CONFIG_ASSOC_KEY => [
                            [
                                'rootA',
                                [
                                    ProjectConfig::CONFIG_ASSOC_KEY => [
                                        ['label', 'childA']
                                    ]
                                ]
                            ],
                            [
                                'rootB',
                                [
                                    ProjectConfig::CONFIG_ASSOC_KEY => [
                                        ['label', 'childB']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    public function cleanupConfigDataProvider()
    {
        return [
            [
                [
                    'empty' => [],
                    'emptier' => '',
                    'gone' => null,
                    'obj' => (object)['okay'],
                ],
                [
                    'emptier' => '',
                    'gone' => null,
                    'obj' => ['okay']
                ],
            ],
            [
                [
                    'plainSettings' => 'plain',
                    'other settings' => [
                        'some' => 'thing',
                        'foo' => ['bar', 'baz']
                    ],
                    'randomArray' => [1, 7, 2, 'ok']
                ],
                [
                    'other settings' => [
                        'foo' => ['bar', 'baz'],
                        'some' => 'thing'
                    ],
                    'plainSettings' => 'plain',
                    'randomArray' => [1, 7, 2, 'ok']
                ],
            ]
        ];
    }

    public function splitIntoComponentsProvider()
    {
        return [
            [
                [
                    'dateModified' => 1,
                    'email' => [
                        'provider' => 'gmail'
                    ]
                ],
                [
                    'project.yaml' => [
                        'dateModified' => 1,
                        'email' => [
                            'provider' => 'gmail'
                        ]
                    ]
                ],
            ],
            [
                [
                    'dateModified' => 2,
                    'email' => [
                        'provider' => 'gmail',
                        'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => [
                            'key' => 'value'
                        ]
                    ]
                ],
                [
                    'project.yaml' => [
                        'dateModified' => 2,
                        'email' => [
                            'provider' => 'gmail',
                            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => [
                                'key' => 'value'
                            ]
                        ]
                    ]
                ],
            ],
            [
                [
                    'dateModified' => 3,
                    'email' => [
                        'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => [
                            'key' => 'value'
                        ]
                    ]
                ],
                [
                    'project.yaml' => [
                        'dateModified' => 3
                    ],
                    'email/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa.yaml' => [
                        'key' => 'value'
                    ]
                ],
            ],
            [
                [
                    'dateModified' => 4,
                    'email' => [
                        'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => [
                            'key' => 'value'
                        ],
                        'bbbbbbbb-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => [
                            'key2' => 'value'
                        ]
                    ]
                ],
                [
                    'project.yaml' => [
                        'dateModified' => 4
                    ],
                    'email/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa.yaml' => [
                        'key' => 'value'
                    ],
                    'email/bbbbbbbb-aaaa-aaaa-aaaa-aaaaaaaaaaaa.yaml' => [
                        'key2' => 'value'
                    ]
                ],
            ],
            [
                [
                    'dateModified' => 4, 'email' => [
                    'aaaaaaaG-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => [
                        'key' => 'value'
                    ],
                    'bbbbbbbb-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => [
                        'key2' => 'value'
                    ]
                ]
                ],
                [
                    'project.yaml' => [
                        'dateModified' => 4,
                        'email' => [
                            'aaaaaaaG-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => [
                                'key' => 'value'
                            ],
                            'bbbbbbbb-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => [
                                'key2' => 'value'
                            ]
                        ]
                    ]
                ],
            ],
        ];
    }
}
