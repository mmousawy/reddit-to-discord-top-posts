# Reddit top posts to Discord

Extract and show posts from your favourite subreddit in a Discord channel with this script.

![Screenshot of Reddit top posts to Discord in action](/img/screenshot.png)

## Config

Setup the script by adding a `config.json` file with the following contents:

```jsonc
[
  {
    "webhookUrl": "https://discordapp.com/api/webhooks/...",
    "redditUrl": "https://old.reddit.com/r/webdev/top/?sort=top&t=day",
    "postAmount": 5
  },

  // Add more webhooks and subreddits...
]
```
A sample config file can be found in the repo: [config.sample.json](https://github.com/mmousawy/reddit-top-posts-discord/blob/master/config.sample.json)

You're able to set values for the following keys:

| Key           | Possible value |
| ------------- | ------------- |
| `webhookUrl`    | _(string)_ Unique URL created by Discord (see https://support.discordapp.com/hc/en-us/articles/228383668-Intro-to-Webhooks) |
| `redditUrl`     | _(string)_ Subreddit URL that ends with `?sort=top&t=day` to guarantee you get the top posts |
| `postAmount`    | _(int)_ `1` - `25` |

## Running the script

I personally use `crontab` to setup a cronjob for every day at 06:00 in the morning (with timezone difference this corresponds roughly to 00:00 EST).

To add a cronjob, you can add one of the following lines to the crontab file.

First, open crontab:

```bash
crontab -e
```

Then add command to run PHP, referring to the script file:

```bash
# Reddit top posts to Discord every day at 06:00
0 6 * * * /bin/php /var/www/cron/reddit-to-discord/index.php
```

If you want logs to be activated:

```bash
0 6 * * * /bin/php /var/www/cron/reddit-to-discord/index.php >> /var/www/cron/reddit-to-discord/log 2>&1
```

Or if you need verbose debug logs:
```bash
0 6 * * * /bin/php /var/www/cron/reddit-to-discord/index.php?debug >> /var/www/cron/reddit-to-discord/log 2>&1
```

Need help understanding cronjobs? For a nice visual representation of the crontab syntax, check out: https://crontab.guru.

## Donate
Please consider donating if you think this script is helpful to you or that my work is valuable. I am happy if you can [help me buy a cup of coffee](https://paypal.me/MalMousawy). ☕️
