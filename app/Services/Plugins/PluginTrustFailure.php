<?php

namespace App\\Services\\Plugins;

use RuntimeException;

/**
 * A permanent plugin trust/integrity rejection.
 *
 * Queue middleware may acknowledge and drop work for this exception. Transient
 * storage, database, filesystem and runtime failures must remain ordinary
 * exceptions so the queue worker retries them.
 */
final class PluginTrustFailure extends RuntimeException
{
}
