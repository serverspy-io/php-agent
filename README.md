# ServerSpy PHP Agent
Website backup, antivirus, and monitoring all through a single PHP file. 

### How to Install
Installing [ServerSpy](https://serverspy.io/) is easy, all you need is a web server that supports PHP and has [php-cURL](http://php.net/manual/en/book.curl.php) installed. This is more than 99% of all PHP installations wordlwide. No need to give us your password, everything is securely handled through the PHP agent.

1. Download the [latest version of the backup agent](https://raw.githubusercontent.com/serverspy-io/php-agent/master/serverspy.php), and place it anywhere within your website that is web-accessible. 
2. Signup for a [ServerSpy](https://serverspy.io/#get-started) account. During the setup process you will let us know where you loaded the serverspy.php file.
3. You're done. You will immideatly be able to see the contents of your server, and within a few minutes a full backup should have finished. 

### Certified PHP/OS Combinations

| PHP   | Ubuntu 16 | Ubuntu 14 | Ubuntu 12 | CentOS 7 | CentOS 6 | Fedora 24 | Fedora 23 |
| ----- |:---------:|:---------:|:---------:|:--------:|:--------:|:---------:|:---------:|
| 7.0   | X         | X         | X         | X        | X        | X         | X         |
| 5.6   | X         | X         | X         | X        | X        | X         | X         |
| 5.5   | X         | X         | X         | X        | X        | X         | X         |
| 5.4   | X         | X         | X         | X        | X        | X         | X         |
| 5.3   | X         | X         | X         | X        | X        | X         | X         |

#### Do I need HTTPS for my data to be transferred securely?
No. We use a token based callback system, where we send a token with a 5 second life to your server, which initiates a callback to us over an HTTPS connection. At which point the task is given, and any data is accepted only over this secure connection. Even if this token is intercepted in transit, there is no identifiable data contained within the token.

##### PHP Below 5.3
PHP versions 5.0, 5.1, and 5.2 should work with [ServerSpy](https://serverspy.io/). With that being said, the age of these distributions is so old that doing efficient testing is not currently practical. If you get it to work, please be sure to leave us [feedback](mailto:hello@serverspy.io). 
