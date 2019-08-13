<?php

require_once __DIR__ . "/vendor/autoload.php";

use \Symfony\Component\Yaml\Yaml;

$image = "library/redis";
$excludedTags = ['windowsservercore', 'nanoserver'];

$client = new \GuzzleHttp\Client();

$allLoaded = false;
$results = [];
$url = "https://hub.docker.com/v2/repositories/{$image}/tags/";
while($allLoaded == false) {
    $data = $client->get($url)->getBody()->getContents();
    $json = json_decode($data, true);

    $results = array_merge($results, $json['results']);
    if($json['next']){
        $url = $json['next'];
    }else{
        $allLoaded = true;
    }
}

$travisYaml = [
    'language' => 'bash',
    'notifications' => [
        'email' => [
            'matthew@baggett.me',
        ]
    ],
    'before_script' => [
        'docker login -u $DOCKER_LOGIN -p $DOCKER_PASSWORD $DOCKER_REGISTRY',
        'sudo rm /usr/local/bin/docker-compose',
        'curl -L https://github.com/docker/compose/releases/download/1.21.0/docker-compose-`uname -s`-`uname -m` > docker-compose',
        'chmod +x docker-compose',
        'sudo mv docker-compose /usr/local/bin',
    ],
    'script' => [
        'docker-compose -f build.yml build redis-$VERSION'
    ],
    'after_script' => [
        'docker-compose -f build.yml push redis-$VERSION'
    ],
    'env' => [],
];

$buildYaml = [
    'version' => (string) '2.3',
    'services' => []
];

foreach($results as $result) {
    if(in_array($result['name'], $excludedTags))
        continue;

    $dockerfileLines = [];
    $dockerfileLines[] = "# From upstream {$result['name']}";
    $dockerfileLines[] = "FROM redis:{$result['name']}";
    $dockerfileLines[] = "# Add healthcheck";
    $dockerfileLines[] = "HEALTHCHECK --interval=30s --timeout=3s \\";
    $dockerfileLines[] = "  CMD redis-cli PING ";

    $dockerfile = "generated/Dockerfile.{$result['name']}";
    $buildYaml['services']['redis-' . $result['name']] = [
        'build' => [
            'context' => '.',
            'dockerfile' => $dockerfile
        ],
        'image' => "benzine/redis:{$result['name']}",
    ];

    $travisYaml['env'][] = "VERSION={$result['name']}";

    file_put_contents($dockerfile, implode("\n", $dockerfileLines));
}

sort($travisYaml['env']);
$travisYaml['env'] = array_slice($travisYaml['env'],-200,200);

file_put_contents("build.yml", Yaml::dump($buildYaml));

file_put_contents(".travis.yml", Yaml::dump($travisYaml));