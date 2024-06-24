<?php

namespace Src\Utils;
use finfo;
use JsonSchema\Validator as JsonSchemaValidator;

class MessageValidator
{
    private static ?object $messageSchema = null;
    private static ?finfo $finfo = null;

    public function __construct()
    {
        self::getMessageSchema();
    }

    /**
     * Validate a message to be sent through the WebSocket
     * @param string $message message to be validated. Expected to be a JSON string.
     * @throws \Exception if the message is invalid
     * @return string the validated message
     */
    public function validate($message): string
    {  
        self::validateAgainstSchema($message);
        self::validateBasedOnType($message);

        return $message;
    }

    private static function validateAgainstSchema(string $data): void
    {
        $data = json_decode($data);

        $validator = new JsonSchemaValidator;
        $validator->validate($data, self::$messageSchema);

        if (! $validator->isValid()) {
            $errorMessage = '[JSON-SCHEMA] ';
            
            foreach ($validator->getErrors() as $error) {
                $errorMessage .= " | " . $error['message'];
            }

            throw new \Exception($errorMessage);
        }
    }

    private static function validateBasedOnType(string $data): string
    {
        $decodedData = json_decode($data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON: ' . json_last_error_msg());
        }

        if (! isset($decodedData['type'])) {
            throw new \Exception('Message type field not found');
        }

        if (! isset($decodedData['content'])) {
            throw new \Exception('Message content field not found');
        }

        if (isset($decodedData['content'])) {
            switch ($decodedData['type']) {
                case 'join_room':
                    if (strlen($decodedData['content']) > 25) {
                        throw new \Exception('Notification content is too long. Max length is 512 characters.');
                    }

                    $safe_notification = htmlspecialchars($decodedData['content'], ENT_QUOTES, 'UTF-8');
                    $decodedData['content'] = $safe_notification;
                    break;
                case 'leave_room':
                    if (strlen($decodedData['content']) > 25) {
                        throw new \Exception('Notification content is too long. Max length is 512 characters.');
                    }

                    $safe_notification = htmlspecialchars($decodedData['content'], ENT_QUOTES, 'UTF-8');
                    $decodedData['content'] = $safe_notification;
                    break;
                case 'message':
                    if (strlen($decodedData['content']) > 65535) {
                        throw new \Exception('Message content is too long');
                    }

                    $safe_text = htmlspecialchars($decodedData['content'], ENT_QUOTES, 'UTF-8');
                    $decodedData['content'] = $safe_text;
                    break;
                case 'document':
                    if (! base64_decode($decodedData['content'], true)) {
                        throw new \Exception('Invalid base64 encoding');
                    }

                    $document_data = base64_decode($decodedData['content']);
                    
                    if (strlen($document_data) > 1048576) {
                        throw new \Exception('Document size is greater than 1MB');
                    }

                    $acceptedMimeTypes = ['application/pdf'];

                    $finfo = self::getFinfoInstance();
                    $mimeType = $finfo->buffer($document_data);

                    if (! in_array($mimeType, $acceptedMimeTypes)) {
                        throw new \Exception('Invalid document type: ' . $mimeType . '. Only PDF files are allowed.');
                    }

                    break;
                case 'image':
                    if (! base64_decode($decodedData['content'], true)) {
                        throw new \Exception('Invalid base64 encoding');
                    }

                    $image_data = base64_decode($decodedData['content']);
                    if (strlen($image_data) > 1048576) {
                        throw new \Exception('Image size is too large');
                    }

                    $finfo = self::getFinfoInstance();
                    $mimeType = $finfo->buffer($image_data);
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

                    if (! in_array($mimeType, $allowedTypes)) {
                        throw new \Exception('Invalid image type');
                    }

                    break;
                case 'notification':
                    if (strlen($decodedData['content']) > 512) {
                        throw new \Exception('Notification content is too long. Max length is 512 characters.');
                    }

                    $safe_notification = htmlspecialchars($decodedData['content'], ENT_QUOTES, 'UTF-8');
                    $decodedData['content'] = $safe_notification;
                    break;
                default:
                    throw new \Exception('Invalid message type');
            }
        }

        return json_encode($decodedData);
    }

    private static function getMessageSchema(): void
    {
        if (self::$messageSchema === null) {
            $schemaPath = __DIR__ . '/../JsonSchemas/MessageSchema.json';
            $messageSchema = @file_get_contents($schemaPath);

            if (! $messageSchema) {
                throw new \Exception('Message schema not found');
            }

            $data = json_decode($messageSchema);

            if (!$data && json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid message schema: ' . json_last_error_msg());
            } else {
                self::$messageSchema = $data;
            }
        }
    }

    private static function getFinfoInstance(): finfo
    {
        if (self::$finfo === null) {
            self::$finfo = new finfo(FILEINFO_MIME_TYPE);
        }

        return self::$finfo;
    }
}