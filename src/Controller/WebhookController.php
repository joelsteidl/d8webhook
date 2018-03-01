<?php

namespace Drupal\d8webhook\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class WebhookController.
 */
class WebhookController extends ControllerBase {

  /**
   * Drupal\Core\Logger\LoggerChannelFactory definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * Drupal\Core\Queue\QueueFactory definition.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Enable or disable debugging.
   *
   * @var bool
   */
  protected $debug = FALSE;

  /**
   * Secret to compare against a passed token.
   *
   * Implementing https://www.drupal.org/project/key
   * is a stronger approach.
   * In this example, you would need $config['d8webhooks']['token'] = 'yourtokeninsettingsphp'; in settings.php.
   *
   * @var string
   */
  protected $secret = \Drupal::service('config.factory')->get('d8webhooks')->get('token');

  /**
   * Constructs a new WebhookController object.
   */
  public function __construct(LoggerChannelFactory $logger, QueueInterface $queue) {
    $this->logger = $logger->get('d8webhook');
    $this->queue = $queue;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('queue')->get('process_payload_queue_worker')
    );
  }

  /**
   * Capture the payload.
   *
   * @return Symfony\Component\HttpFoundation\Response
   *   A simple string and 200 response.
   */
  public function capture(Request $request) {
    // Keep things fast.
    // Don't load a themed site for the response.
    // Most Webhook providers just want a 200 response.
    $response = new Response();

    // Capture the payload.
    // Option 2: $payload = file_get_contents("php://input");.
    $payload = $request->getContent();

    // Check if it is empty.
    if (empty($payload)) {
      $message = 'The payload was empty.';
      $this->logger->error($message);
      $response->setContent($message);
      return $response;
    }

    // Use temporarily to inspect payload.
    if ($this->debug) {
      $this->logger->debug('<pre>@payload</pre>', ['@payload' => $payload]);
    }

    // Add the $payload to our defined queue.
    $this->queue->createItem($payload);

    $response->setContent('Success!');
    return $response;
  }

  /**
   * Simple authorization using a token.
   *
   * @param string $token
   *    A random token only your webhook knows about.
   *
   * @return AccessResult
   *   AccessResult allowed or forbidden.
   */
  public function authorize($token) {
    if ($token === $this->secret) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
