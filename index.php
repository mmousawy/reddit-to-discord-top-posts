<?php

/**
 * Extract post info from subreddit page HTML
 */
class RedditExtractor
{
  /**
   * Constructor.
   *
   * @param  string $url Subreddit URL to read and extract data from
   *
   * @return void
   */
  function __construct(string $url) {
    $this->html = file_get_contents($url);
  }

  /**
   * Extract info from reddit posts with a bunch of regex.
   *
   * @param  int $postAmount Amount of posts to extract, between 1 and 25
   *
   * @return array
   */
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

/**
 * Convert Reddit post to Discord-friendly object.
 */
class RedditPostToDiscordEmbed
{
  /**
   * Constructor.
   *
   * @param  array $post Reddit post object to be converted to Discord-friendly object
   *
   * @return void
   */
  function __construct(array $post)
  {
    $this->post = $post;
  }

  /**
   * Convert Reddit post to Discord-friendly object.
   *
   * @return array
   */
  function convert(): array
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

/**
 * Setup the webhook with CURL and post the data.
 */
class DiscordWebhookPost
{
  /**
   * Constructor.
   *
   * @param  string $webhookUrl The Discord webhook URL to send the data to.
   * @param  array $data The data to be sent to the webhook.
   *
   * @return void
   */
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
        'Length: ' . strlen($hookObject),
        'Content-Type: application/json'
      ]
    ]);
  }

  /**
   * Execute the CURL command and post the data.
   *
   * @return bool
   */
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

echo '>> STARTING - ' . date('Y-m-d H:m:s') . PHP_EOL;

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

foreach ($config as $subredditConfig) {
  $extractor = new RedditExtractor($subredditConfig['subredditUrl']);
  $extractedData = $extractor->extract();

  $postsData = array_map(function($extractedPost) use ($config) {
    $embed = new RedditPostToDiscordEmbed($extractedPost);
    return $embed->convert();
  }, $extractedData);

  var_dump($postsData);

  $discordData = [
    'username' => 'Reddit top posts to Discord',
    'avatar_url' => 'https://mmousawy.github.io/reddit-top-posts-discord/img/avatar.png',
    'content' => "Today's top " . $subredditConfig['postAmount'] . ' posts from https://reddit.com/r/' . $extractedData[0]['subreddit'],
    'embeds' => $postsData
  ];

  $webhookPost = new DiscordWebhookPost($subredditConfig['webhookUrl'], $discordData);
  $response = $webhookPost->post();

  if (!$response) {
    echo '- Error posting for: ' . $extractedData[0]['subreddit'] . PHP_EOL;
  } else {
    echo '- Success posting for: ' . $extractedData[0]['subreddit'] . ' -> ' . $response . PHP_EOL;
  }
}

echo '>> DONE - ' . date('Y-m-d H:m:s') . PHP_EOL . PHP_EOL;
