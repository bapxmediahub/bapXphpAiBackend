<?php
declare(strict_types=1);

use App\Services\{AgentContextService, PublicA2uiService, PublicKnowledgeService, SupportBotService};

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__) . '/app/bootstrap.php';

$checks = 0;
$expect = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$ring = ['name'=>'Sri Varahi Amman Ring', 'slug'=>'varahi-amman-ring', 'price'=>899,
    'description'=>'Five-metal devotional ring.', 'specifications'=>['Material'=>'Panchaloha', 'Package'=>'1 ring']];
$pendant = ['name'=>'Varahi Amman Pendant', 'slug'=>'varahi-amman-pendant', 'price'=>999,
    'description'=>'Devotional pendant.', 'specifications'=>['Package'=>'1 pendant']];
$unrelated = ['name'=>'Other Sacred Ring', 'slug'=>'other-sacred-ring', 'price'=>399];
$products = [$unrelated, $pendant, $ring];
$question = 'Compare the Sri Varahi Amman Ring and the Varahi Amman Pendant.';
$selection = AgentContextService::catalogueSelection($products, $question);
$expect($selection['status'] === 'matched' && $selection['reason'] === 'exact_comparison_aliases', 'A named lookup resolves exact comparison operands.');
$expect($selection['products'] === [$ring, $pendant], 'Only named products in mention order may be compared.');
$project = static fn(array $product): array => [
    'name'=>$product['name'],'path'=>PublicKnowledgeService::productPath($product['slug']),
    'priceInr'=>$product['price'],'available'=>true,
    'specifications'=>array_map(static fn(string $label,string $value):array=>['label'=>$label,'value'=>$value],
        array_keys($product['specifications']),array_values($product['specifications'])),
];
$presenter = new PublicA2uiService();
$cards = $presenter->fromResult('products',['products'=>array_map($project,$selection['products'])],2,'compare',2);
$items = $cards['messages'][0]['createSurface']['dataModel']['items'];
$expect(array_column($items,'priceInr') === [899.0,999.0], 'The public comparison displays the exact typed prices.');
$expect($items[0]['specifications'][0] === ['label'=>'Material','value'=>'Panchaloha']
    && $items[1]['specifications'][1] === ['label'=>'Package','value'=>'1 pendant'], 'Comparison preserves the listed specifications.');
$expect(array_column($items,'title') === [$ring['name'],$pendant['name']], 'Verified comparison cannot substitute unrelated choices.');
$reply = 'Compare /product/varahi-amman-ring and /product/varahi-amman-pendant.';
$expect(SupportBotService::shownProductSlugs($reply, $selection['products']) === [$ring['slug'], $pendant['slug']], 'Answer links must establish the exact card and follow-up identities.');
$expect(AgentContextService::namedComparisonProducts($products, 'Varahi Amman Pendant versus varahi-amman-ring') === [$pendant, $ring], 'Names and canonical slug aliases share mention ordering.');

foreach ([
    'Compare the Sri Varahi Amman Ring and the Missing Moon Pendant.',
    'Compare Neptune Ring and Moon Pendant.',
    'Compare Sri Varahi Amman Ring, Varahi Amman Pendant and Missing Moon Ring.',
    'Compare Missing Moon Ring, Sri Varahi Amman Ring and Varahi Amman Pendant.',
    'Compare Sri Varahi Amman Ring, Missing Moon Ring and Varahi Amman Pendant.',
    'Compare Sri Varahi Amman Ring, Varahi Amman Pendant, Other Sacred Ring and Missing Moon Ring.',
] as $unknown) {
    $missing = AgentContextService::catalogueSelection($products, $unknown);
    $expect($missing['status'] === 'clarification' && $missing['products'] === [], 'Missing named products cannot be replaced with random rings or pendants.');
}
$hidden = $pendant + ['is_hidden'=>true];
$expect(AgentContextService::namedComparisonProducts([$ring, $hidden], $question) === [], 'A hidden product is never a public comparison candidate.');
$hiddenThird = $unrelated + ['is_hidden'=>true];
$expect(AgentContextService::namedComparisonProducts([$ring, $pendant, $hiddenThird], 'Compare Sri Varahi Amman Ring, Varahi Amman Pendant and Other Sacred Ring.') === [], 'A hidden third product cannot silently shorten a comparison.');
$expect(AgentContextService::namedComparisonProducts($products, 'Could you compare the Sri Varahi Amman Ring with Varahi Amman Pendant by price and material?') === [$ring, $pendant], 'A requested comparison criterion remains supported.');
foreach ([
    'Compare Sri Varahi Amman Ring and Varahi Amman Pendant. Show their real photos and current prices.',
    'Compare Sri Varahi Amman Ring and Varahi Amman Pendant and show prices.',
    'Compare Sri Varahi Amman Ring and Varahi Amman Pendant. Show me their photos.',
] as $presentationRequest) {
    $expect(AgentContextService::catalogueSelection($products, $presentationRequest)['products'] === [$ring, $pendant],
        'A photo or price presentation request cannot erase verified comparison identities.');
}
$expect(AgentContextService::namedComparisonProducts($products,
    'Compare Sri Varahi Amman Ring, Varahi Amman Pendant and Missing Moon Ring. Show their photos.') === [],
    'A photo qualifier cannot hide an unresolved third comparison operand.');
$invalidSlug = $pendant; $invalidSlug['slug'] = '.varahi-amman-pendant';
$expect(AgentContextService::namedComparisonProducts([$ring, $invalidSlug], $question) === [], 'Native comparison shares the canonical public path grammar.');
$duplicate = $pendant;
$duplicate['slug'] = 'different-varahi-pendant';
$expect(AgentContextService::namedComparisonProducts([$ring, $pendant, $duplicate], $question) === [], 'Ambiguous identical names need clarification.');
$broad = ['name'=>'Copper Ring', 'slug'=>'copper-ring'];
$specific = ['name'=>'Large Copper Ring', 'slug'=>'large-copper-ring'];
$expect(AgentContextService::namedComparisonProducts([$broad, $specific, $pendant], 'Compare Large Copper Ring with Varahi Amman Pendant') === [$specific, $pendant], 'An embedded shorter alias cannot add an unrequested product.');
$three = 'Compare Sri Varahi Amman Ring, Varahi Amman Pendant and Other Sacred Ring.';
$expect(AgentContextService::namedComparisonProducts($products, $three) === [$ring, $pendant, $unrelated], 'Three selected products remain bounded and ordered.');
$fourth = ['name'=>'Fourth Sacred Item', 'slug'=>'fourth-sacred-item'];
$expect(AgentContextService::namedComparisonProducts([...$products, $fourth], rtrim($three, '.') . ' and Fourth Sacred Item.') === [], 'More than three products require a smaller selection.');
$tamil = ['name'=>'அம்மன் வெள்ளி மோதிரம்', 'slug'=>'அம்மன்-வெள்ளி-மோதிரம்'];
$expect(AgentContextService::namedComparisonProducts([$tamil, $pendant], 'Compare அம்மன் வெள்ளி மோதிரம் and Varahi Amman Pendant') === [$tamil, $pendant], 'Unicode product names must retain exact identity.');
$tamilCollectionName = ['name'=>'அய்யப்பன் சுவாமி மாலை', 'slug'=>'அய்யப்பன்-சுவாமி-மாலை'];
$expect(AgentContextService::catalogueSelection([$tamilCollectionName, $pendant], 'Compare அய்யப்பன் சுவாமி மாலை and Varahi Amman Pendant')['products'] === [$tamilCollectionName, $pendant], 'A word also used by a collection cannot preempt an explicit named comparison.');
$expect(AgentContextService::namedComparisonProducts($products, 'Show the Sri Varahi Amman Ring and Varahi Amman Pendant') === [], 'Two product mentions alone do not imply comparison layout.');
$mala = ['name'=>'Ayyappa Dollar with Impon Malai', 'slug'=>'ayyappa-dollar-with-impon-malai'];
$generic = AgentContextService::catalogueSelection([$pendant, $mala], 'Can you explain the difference between a pendant and a mala for everyday devotional use?');
$expect($generic['products'] === [$pendant, $mala] && $generic['reason'] === 'comparison_examples', 'Existing explicit generic-type comparisons still use labelled examples.');
$missingPrice = $ring;
$missingPrice['price'] = null;
$missingPrice['specifications'] = [];
$partial = $presenter->fromResult('products',['products'=>array_map($project,[$missingPrice,$pendant])],2,'compare',2);
$partialModel = $partial['messages'][0]['createSurface']['dataModel'];
$expect($partialModel['presentation'] === 'product_detail' && array_column($partialModel['items'],'title') === [$pendant['name']],
    'Missing price cannot be presented as a comparison fact or silently replaced.');
echo "PASS catalogue named comparison: {$checks} checks\n";
