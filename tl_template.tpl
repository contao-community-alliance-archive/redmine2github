<?php

/**
 * redmine2github
 * Copyright (C) 2012 MEN AT WORK
 *
 * PHP version 5
 * @copyright MEN AT WORK 2012
 * @author Andreas Isaak <cms@men-at-work.de>
 * @author Stefan Heimes <cms@men-at-work.de>
 * @author Yanick Witschi <yanick.witschi@certo-net.ch>
 * @package redmine2github
 * @license LGPL
 * @filesource
 */
?>

$arrTicketRelations = array
(
	<?php foreach ($arrRelations as $new => $old): ?>
	'<?php echo $old; ?>' => '<?php echo $new; ?>',
	<?php endforeach; ?>
);
