<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\mail\transportadapters;

use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;

/**
 * Smtp implements a SMTP transport adapter into Craft’s mailer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class Smtp extends BaseTransportAdapter
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'SMTP';
    }

    /**
     * @var string|null The host that should be used
     */
    public $host;

    /**
     * @var string|null The port that should be used
     */
    public $port;

    /**
     * @var bool|string|null Whether to use authentication
     */
    public $useAuthentication;

    /**
     * @var string|null The username that should be used
     */
    public $username;

    /**
     * @var string|null The password that should be used
     */
    public $password;

    /**
     * @var string|null The encryption method that should be used, if any (ssl or tls)
     */
    public $encryptionMethod;

    /**
     * @var string The timeout duration (in seconds)
     */
    public $timeout = 10;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'host',
                'port',
                'useAuthentication',
                'username',
                'password',
                'encryptionMethod',
            ],
        ];
        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'host' => Craft::t('app', 'Hostname'),
            'port' => Craft::t('app', 'Port'),
            'useAuthentication' => Craft::t('app', 'Use authentication'),
            'username' => Craft::t('app', 'Username'),
            'password' => Craft::t('app', 'Password'),
            'encryptionMethod' => Craft::t('app', 'Encryption Method'),
            'timeout' => Craft::t('app', 'Timeout'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['host'], 'trim'];
        $rules[] = [['host', 'port', 'timeout'], 'required'];
        $rules[] = [
            ['username', 'password'],
            'required',
            'when' => function($model) {
                /** @var self $model */
                return App::parseBooleanEnv($model->useAuthentication) ?? false;
            },
        ];
        $rules[] = [['encryptionMethod'], 'in', 'range' => ['none', 'tls', 'ssl']];
        $rules[] = [['timeout'], 'number', 'integerOnly' => true];
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('_components/mailertransportadapters/Smtp/settings', [
            'adapter' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function defineTransport()
    {
        $config = [
            'class' => \Swift_SmtpTransport::class,
            'host' => App::parseEnv($this->host),
            'port' => App::parseEnv($this->port),
            'timeout' => $this->timeout,
        ];

        if (App::parseBooleanEnv($this->useAuthentication) ?? false) {
            $config['username'] = App::parseEnv($this->username);
            $config['password'] = App::parseEnv($this->password);
        }

        if ($this->encryptionMethod) {
            $encryptionMethod = App::parseEnv($this->encryptionMethod);
            if ($encryptionMethod && $encryptionMethod !== 'none') {
                $config['encryption'] = $encryptionMethod;
            }
        }

        return $config;
    }
}
