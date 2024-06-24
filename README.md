### Hey there!

`Version 0.0.1`

This project is a simple websocket server made using Swoole and PHP 8.3.
Feel free to add extra features if you wish.

This project was made for studying purposes only, but can be used in small projects if security is correctly taken into account.

------------


#### Features
- **authentication** using `firebase/JWT`
- **configuration file** for easy setup
- **.env file support** for environment variables
- **basic message validation** including:
  - JSON schema validation
  - MIME types validation
  - Basic string validations

------------


#### Installation/Requirements
- **PHP** ^8.0
- **Swoole extension** ^5.0 (Check the [docs](https://wiki.swoole.com/en/#/environment) for installation instructions)
- **Clone** the project and run `composer install`
- **Configure** your .env file, specially the **log** file directory.
------------


#### Usage
- Run `php public/index.php` to start the server.
- Make sure the message body fullfill the requirements for MessageSchema.json.
- Example client-side message body:
```json
{
	  "type": "message",
	  "content": "Hello, World!",
	  "sender": {
			"id": "123",
			"name": "John Doe"
	  },
	  "token": "your-jwt-token",
	  "timestamp": "2024-06-11T10:00:00Z",
	  "metadata": {
			"messageId": "605391",
			"roomId": "45"
	  }
}
```

------------



All the best! :)
