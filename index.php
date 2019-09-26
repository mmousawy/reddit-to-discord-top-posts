<?php

class RedditExtractor
{
  function __construct(string $url) {
    $this->html = file_get_contents($url);
  }

  function extract(int $postAmount = 5): array
  {
    $postRegex = '/class=" thing.+?(?=class=" thing|view more:)/m';

    preg_match_all($postRegex, $this->html, $matches, PREG_SET_ORDER, 0);

    $posts = [];

    // Loop through the posts
    if (count($matches) > 0) {
      for ($postIndex = 0; $postIndex < $postAmount; $postIndex++) {
        $match = $matches[$postIndex];

        if (isset($_GET['debug'])) {
          echo '>> FOUND POST ' . $postIndex . ':' . PHP_EOL . PHP_EOL;
          var_dump($match[0]);
          echo PHP_EOL . PHP_EOL;
        }

        if (!isset($match)) {
          break;
        }

        $post = [];

        $postValuesRegex = [
          'title' => '/data-event-action="title".+?>(.+?)</',
          'upvotes' => '/score unvoted" title="(\d+?)"/',
          'subreddit' => '/data-subreddit="(.+?)"/',
          'comments' => '/data-comments-count="(\d+?)"/',
          'author' => '/data-author="(.+?)"/',
          'permalink' => '/data-permalink="(.+?)"/',
          'thumbnail' => '/action="thumbnail".+?<img src="(.+?)"/',
          'date' => '/datetime="(.+?)"/'
        ];

        foreach ($postValuesRegex as $key => $valueRegex) {
          preg_match($valueRegex, $match[0], $matchedValue, PREG_OFFSET_CAPTURE, 0);

          $value = '';

          if (isset($matchedValue[1][0])) {
            if ($key === 'date') {
              $value = substr($matchedValue[1][0], 0, 19);
            } else if ($key === 'thumbnail') {
              // Check if protocol is defined in URL, otherwise add it
              if (strpos($matchedValue[1][0], 'http') === false) {
                $value = 'https:' . $matchedValue[1][0];
              } else {
                $value = $matchedValue[1][0];
              }
            } else {
              $value = $matchedValue[1][0];
            }
          } else {
            if ($key === 'thumbnail') {
              $value = 'https://mmousawy.github.io/reddit-top-posts-discord/img/text-placeholder.png';
            }
          }

          $post[$key] = $value;
        }

        array_push($posts, $post);
      }
    }

    if (isset($_GET['debug'])) {
      echo '>> EXTRACTED DATA:' . PHP_EOL . PHP_EOL;
      var_dump($posts);
      echo PHP_EOL . PHP_EOL;
    }

    return $posts;
  }
}

class RedditPostToDiscordEmbed
{
  function __construct(array $post)
  {
    $this->post = $post;
  }

  function create(): array
  {
    return [
      'author' => [
        'name' => $this->post['author'],
        'url' => 'https://www.reddit.com/u/' . $this->post['author']
      ],
      'title' => $this->post['title'],
      'description' => $this->post['upvotes']. ' upvotes - ' . $this->post['comments'] . ' comments',
      'url' => 'https://reddit.com' . $this->post['permalink'],
      'thumbnail' => [
        'url' => $this->post['thumbnail']
      ],
      'timestamp' => $this->post['date']
    ];
  }
}


class DiscordWebhookPost
{
  function __construct(string $webhookUrl, array $data)
  {
    $hookObject = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (isset($_GET['debug'])) {
      echo '>> WEBHOOK OBJECT:' . PHP_EOL . PHP_EOL;
      echo $hookObject . PHP_EOL . PHP_EOL;
    }

    $this->curl = curl_init($webhookUrl);

    curl_setopt_array($this->curl, [
      CURLOPT_URL            => $webhookUrl,
      CURLOPT_POST           => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS      => 10,
      CURLOPT_HEADER         => true,
      CURLOPT_POSTFIELDS     => $hookObject,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => [
        'Length' => strlen($hookObject),
        'Content-Type' => 'application/json'
      ]
    ]);
  }

  function post(): bool
  {
    $response = curl_exec($this->curl);

    if (isset($_GET['debug'])) {
      echo '>> POST RESPONSE:' . PHP_EOL . PHP_EOL;
      echo $response . PHP_EOL . PHP_EOL;
    }

    curl_close($this->curl);

    return $response;
  }
}

echo '>> STARTING - ' . date() . PHP_EOL . PHP_EOL;

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

foreach ($config as $subredditConfig) {
  $extractor = new RedditExtractor($subredditConfig['subredditUrl']);
  $extractedData = $extractor->extract();

  $postsData = array_map(function($extractedPost) use ($config) {
    $embed = new RedditPostToDiscordEmbed($extractedPost);
    return $embed->create();
  }, $extractedData);

  $discordData = [
    'username' => 'Reddit top posts to Discord',
    'avatar_url' => 'https://mmousawy.github.io/reddit-top-posts-discord/img/avatar.png',
    'content' => "Today's top " . $subredditConfig['postAmount'] . ' posts from https://reddit.com/r/' . $extractedData[0]['subreddit'],
    'embeds' => $postsData
  ];

  $webhookPost = new DiscordWebhookPost($subredditConfig['webhookUrl'], $discordData);
  $response = $webhookPost->post();

  if (!$response) {
    echo 'Error posting for: ' . $extractedData[0]['subreddit'] . PHP_EOL;
  } else {
    echo 'Success posting for: ' . $extractedData[0]['subreddit'] . ' -> ' . $response . PHP_EOL;
  }
}

echo '>> DONE - ' . date() . PHP_EOL . PHP_EOL;
