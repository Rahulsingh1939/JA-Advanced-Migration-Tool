<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_cmsmigrator
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\CmsMigrator\Administrator\Event;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Event\Result\ResultAware;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Event\Result\ResultTypeMixedAware;

/**
 * Event class for migration operations.
 * Results are stored under arguments['result'] via the ResultAware trait.
 */
final class MigrationEvent extends AbstractEvent implements ResultAwareInterface
{
    use ResultAware;
    use ResultTypeMixedAware; // accept any result type

    public function getResults(): array
    {
        return $this->getArgument('result', []);
    }
}
