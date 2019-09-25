<?php

class RedditExtractor
{
  function __construct(string $url) {
    $this->html = file_get_contents($url);
  }

  function extract(): array
  {
    $regex = '/class=" thing.+?(?=class=" thing|view more:)/m';

    preg_match_all($regex, $this->html, $matches, PREG_SET_ORDER, 0);

    $posts = [];

    // Loop through the posts
    if (count($matches) > 0) {
      foreach ($matches as $match) {
        $post = [];

        $postRegex = [
          'title' => '/data-event-action="title".+?>(.+?)</',
          'upvotes' => '/score unvoted" title="(\d+?)"/',
          'subreddit' => '/data-subreddit="(.+?)"/',
          'comments' => '/data-comments-count="(\d+?)"/',
          'author' => '/data-author="(.+?)"/',
          'permalink' => '/data-permalink="(.+?)"/',
          'thumbnail' => '/action="thumbnail".+<img src="(.+?)"/',
          'date' => '/datetime="(.+?)"/'
        ];

        foreach ($postRegex as $key => $regex) {
          preg_match($regex, $match[0], $matchedValue, PREG_OFFSET_CAPTURE, 0);

          $value = '';

          if (isset($matchedValue[1][0])) {
            $value = $matchedValue[1][0];
          }

          $post[$key] = $value;
        }

        array_push($posts, $post);
      }
    }

    return $posts;
  }
}

// TODO: Setup extractors for each subreddit from config.json
$extractor = new RedditExtractor('https://old.reddit.com/r/HuntShowdown/top/?sort=top&t=day');
$extractedData = $extractor->extract();
var_dump($extractedData);

$webhookUrl = '';

for ($postIndex = 0; $postIndex < 5; $postIndex++) {
  // TODO: Setup and POST digestible object to Discord
}
