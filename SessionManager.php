<?php
declare(strict_types=1);
namespace Viserio\Component\Session;

use Cache\SessionHandler\Psr6SessionHandler;
use ParagonIE\Halite\KeyFactory;
use SessionHandlerInterface;
use Viserio\Component\Contract\Cache\Manager as CacheManagerContract;
use Viserio\Component\Contract\Cache\Traits\CacheManagerAwareTrait;
use Viserio\Component\Contract\Cookie\QueueingFactory as JarContract;
use Viserio\Component\Contract\OptionsResolver\ProvidesDefaultOptions as ProvidesDefaultOptionsContract;
use Viserio\Component\Contract\Session\Exception\RuntimeException;
use Viserio\Component\Contract\Session\Store as StoreContract;
use Viserio\Component\Session\Handler\CookieSessionHandler;
use Viserio\Component\Session\Handler\FileSessionHandler;
use Viserio\Component\Session\Handler\MigratingSessionHandler;
use Viserio\Component\Session\Handler\NullSessionHandler;
use Viserio\Component\Support\AbstractManager;

class SessionManager extends AbstractManager implements ProvidesDefaultOptionsContract
{
    use CacheManagerAwareTrait;

    /**
     * Encryption key instance.
     *
     * @var \ParagonIE\Halite\Symmetric\EncryptionKey
     */
    private $key;

    /**
     * CookieJar instance.
     *
     * @var \Viserio\Component\Contract\Cookie\QueueingFactory
     */
    private $cookieJar;

    /**
     * Create a new session manager instance.
     *
     * @param iterable|\Psr\Container\ContainerInterface $data
     *
     * @throws \ParagonIE\Halite\Alerts\CannotPerformOperation
     * @throws \ParagonIE\Halite\Alerts\InvalidKey
     * @throws \TypeError
     */
    public function __construct($data)
    {
        parent::__construct($data);

        $this->key = KeyFactory::loadEncryptionKey($this->resolvedOptions['key_path']);
    }

    /**
     * Hide this from var_dump(), etc.
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'key' => 'private',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDefaultOptions(): iterable
    {
        return [
            'default'         => 'array',
            'env'             => 'prod',
            'lifetime'        => 7200, // 2 hours
            'encrypt'         => true,
            'drivers'         => [
                'file' => [
                    'path' => __DIR__ . '/session',
                ],
            ],
            'cookie'          => [
                'name'            => 'NSSESSID',
                'path'            => '/',
                'domain'          => null,
                'secure'          => null,
                'http_only'       => true,
                'samesite'        => false,
                'expire_on_close' => false,
                'lottery'         => [2, 100],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getMandatoryOptions(): iterable
    {
        return ['key_path'];
    }

    /**
     * Set the cookie jar instance.
     *
     * @param \Viserio\Component\Contract\Cookie\QueueingFactory $cookieJar
     */
    public function setCookieJar(JarContract $cookieJar): void
    {
        $this->cookieJar = $cookieJar;
    }

    /**
     * Create an instance of the file session driver.
     *
     * @param array $config
     *
     * @return \Viserio\Component\Contract\Session\Store
     */
    protected function createFileDriver(array $config): StoreContract
    {
        return $this->buildSession(
            new FileSessionHandler(
                $config['path'],
                $this->resolvedOptions['lifetime']
            )
        );
    }

    /**
     * Create an instance of the "cookie" session driver.
     *
     * @throws \Viserio\Component\Contract\Session\Exception\RuntimeException
     *
     * @return \Viserio\Component\Contract\Session\Store
     */
    protected function createCookieDriver(): StoreContract
    {
        if ($this->cookieJar === null) {
            throw new RuntimeException(\sprintf('No instance of [%s] found.', JarContract::class));
        }

        return $this->buildSession(
            new CookieSessionHandler(
                $this->cookieJar,
                $this->resolvedOptions['lifetime']
            )
        );
    }

    /**
     * Create an instance of the Array session driver.
     *
     * @return \Viserio\Component\Contract\Session\Store
     */
    protected function createArrayDriver(): StoreContract
    {
        return $this->buildSession(new NullSessionHandler());
    }

    /**
     * Create an instance of the Memcached session driver.
     *
     * @return \Viserio\Component\Contract\Session\Store
     *
     * @codeCoverageIgnore
     */
    protected function createMemcachedDriver(): StoreContract
    {
        return $this->createCacheBased('memcached');
    }

    /**
     * Create an instance of the Memcache session driver.
     *
     * @return \Viserio\Component\Contract\Session\Store
     *
     * @codeCoverageIgnore
     */
    protected function createMemcacheDriver(): StoreContract
    {
        return $this->createCacheBased('memcache');
    }

    /**
     * Create an instance of the Mongodb session driver.
     *
     * @return \Viserio\Component\Contract\Session\Store
     *
     * @codeCoverageIgnore
     */
    protected function createMongodbDriver(): StoreContract
    {
        return $this->createCacheBased('mongodb');
    }

    /**
     * Create an instance of the Predis session driver.
     *
     * @return \Viserio\Component\Contract\Session\Store
     *
     * @codeCoverageIgnore
     */
    protected function createPredisDriver(): StoreContract
    {
        return $this->createCacheBased('predis');
    }

    /**
     * Create an instance of the Redis session driver.
     *
     * @return \Viserio\Component\Contract\Session\Store
     *
     * @codeCoverageIgnore
     */
    protected function createRedisDriver(): StoreContract
    {
        return $this->createCacheBased('redis');
    }

    /**
     * Create an instance of the Filesystem session driver.
     *
     * @return \Viserio\Component\Contract\Session\Store
     */
    protected function createFilesystemDriver(): StoreContract
    {
        return $this->createCacheBased('filesystem');
    }

    /**
     * Create an instance of the APCu session driver.
     *
     * @return \Viserio\Component\Contract\Session\Store
     *
     * @codeCoverageIgnore
     */
    protected function createApcuDriver(): StoreContract
    {
        return $this->createCacheBased('apcu');
    }

    /**
     * Create an instance of the Migrating session driver.
     *
     * @param array $config
     *
     * @throws \Viserio\Component\Contract\Session\Exception\RuntimeException
     *
     * @return \Viserio\Component\Contract\Session\Store
     */
    protected function createMigratingDriver(array $config): StoreContract
    {
        if (! isset($config['current'], $config['write_only'])) {
            throw new RuntimeException('The MigratingSessionHandler needs a current and write only handler.');
        }

        $currentHandler   = $this->getDriver($config['current']);
        $writeOnlyHandler = $this->getDriver($config['write_only']);

        return $this->buildSession(
            new MigratingSessionHandler($currentHandler->getHandler(), $writeOnlyHandler->getHandler())
        );
    }

    /**
     * {@inheritdoc}
     *
     * @codeCoverageIgnore
     */
    protected function callCustomCreator(string $driver, array $options = [])
    {
        return $this->buildSession(parent::callCustomCreator($driver, $options));
    }

    /**
     * Create the cache based session handler instance.
     *
     * @param string $driver
     *
     * @throws \Viserio\Component\Contract\Session\Exception\RuntimeException
     *
     * @return \Viserio\Component\Contract\Session\Store
     */
    protected function createCacheBased($driver): StoreContract
    {
        if ($this->cacheManager === null) {
            throw new RuntimeException(\sprintf('No instance of [%s] found.', CacheManagerContract::class));
        }

        return $this->buildSession(
            new Psr6SessionHandler(
                clone $this->cacheManager->getDriver($driver),
                ['ttl' => $this->resolvedOptions['lifetime'], 'prefix' => 'ns_ses_']
            )
        );
    }

    /**
     * Build the session instance.
     *
     * @param \SessionHandlerInterface $handler
     *
     * @return \Viserio\Component\Contract\Session\Store
     */
    protected function buildSession(SessionHandlerInterface $handler): StoreContract
    {
        if ($this->resolvedOptions['encrypt'] === true) {
            return $this->buildEncryptedSession($handler);
        }

        return new Store($this->resolvedOptions['cookie']['name'], $handler);
    }

    /**
     * Build the encrypted session instance.
     *
     * @param \SessionHandlerInterface $handler
     *
     * @return \Viserio\Component\Contract\Session\Store
     */
    protected function buildEncryptedSession(SessionHandlerInterface $handler): StoreContract
    {
        return new EncryptedStore(
            $this->resolvedOptions['cookie']['name'],
            $handler,
            $this->key
        );
    }

    /**
     * {@inheritdoc}
     */
    protected static function getConfigName(): string
    {
        return 'session';
    }
}
