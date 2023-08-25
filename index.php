<?php
$servername = "localhost";
$username = "root";
$password = ""; // CHANGE ME!
$database = "ezpz";
$header = file_get_contents('header.html');
$conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
$html = $header;
if (isset($_GET['error'])) {
    $html .= '
<div class="alert alert-danger text-center" role="alert">
	' . $_GET['error'] . ' | <a href="#" onclick="window.history.back();">Go Back</a>
</div>';
}

// ================POST================
if ($_POST) {
    // ================MAKE EVENT================
    if (isset($_POST['event'])) {
        $insert = "INSERT INTO `events` (`name`, `host`, `about`, `admin_key`, `open_invite_key`)
			VALUES (:name, :host, :about, :admin, :invite);";
        $result = $conn->prepare($insert);
        $result->bindParam(':name', $_POST['event'], PDO::PARAM_STR);
        $result->bindParam(':host', $_POST['host'], PDO::PARAM_STR);
        $result->bindParam(':about', $_POST['about'], PDO::PARAM_STR);
        $admin = makeToken(50);
        $result->bindParam(':admin', $admin, PDO::PARAM_STR);
        $invite = makeToken(50);
        $result->bindParam(':invite', $invite, PDO::PARAM_STR);
        $result->execute();
        $notice = urlencode('New RSVP event created called ' . $_POST['event']);
        $notify = file_get_contents("http://nervesocket.com/irc/ircq.php?q=" . $notice);
        $html .= '
	<div class="container">
		<div class="jumbotron">
			<h1 class="display-4">EZPZ RSVP</h1>
			<p class="lead">Success! Below is your admin page. Do not lose or share this link.</p>
			<hr class="my-4">
				<a href="./index.php?admin=' . $admin . '">Click Here to go directly do the admin page!</a>
			</hr>
		</div>
	</div>
</body></html>';
    }

    // ================ADMIN INVITE================
    if (isset($_POST['admin_key'])) {
        $sql = "SELECT * FROM `events` WHERE `admin_key` = :admin";
        $result = $conn->prepare($sql);
        $result->bindParam(':admin', $_POST['admin_key'], PDO::PARAM_STR);
        $result->execute();
        $event = $result->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            header('Location: ./index.php?&error=No Admin');
        }
        $insert = "INSERT INTO `guests` (`name`, `email`, `status`, `invite_key`, `approved`, `event_id`)
			VALUES (:name, :email, '" . ($_POST['email'] ? "Pending Email" : "Please Manually Send") . "', :invite, '1', :event);";
        $result = $conn->prepare($insert);
        $result->bindParam(':name', $_POST['name'], PDO::PARAM_STR);
        $result->bindParam(':email', $_POST['email'], PDO::PARAM_STR);
        $invite = makeToken(50);
        $result->bindParam(':invite', $invite, PDO::PARAM_STR);
        $result->bindParam(':event', $event['id'], PDO::PARAM_STR);
        $result->execute();
        header('Location: ./index.php?admin=' . $_POST['admin_key']);
    }

    // ================ADMIN EDIT EVENT================
    if (isset($_POST['edit_key'])) {

        $update = "UPDATE `events` 
			SET `name` = :name, `host` = :host, `about` = :about
			WHERE `admin_key` = :admin_key";
        $result = $conn->prepare($update);
        $result->bindParam(':name', $_POST['editevent'], PDO::PARAM_STR);
        $result->bindParam(':host', $_POST['edithost'], PDO::PARAM_STR);
        $result->bindParam(':about', $_POST['editabout'], PDO::PARAM_STR);
        $result->bindParam(':admin_key', $_POST['edit_key'], PDO::PARAM_STR);
        $result->execute();
        $notice = urlencode('RSVP Event was updated ' . $_POST['editevent']);
        $notify = file_get_contents("http://nervesocket.com/irc/ircq.php?q=" . $notice);
        header('Location: ./index.php?admin=' . $_POST['edit_key']);
    }

    // ================PUBLIC INVITE================
    if (isset($_POST['public'])) {
        $sql = "SELECT * FROM `events` WHERE `open_invite_key` = :key";
        $result = $conn->prepare($sql);
        $result->bindParam(':key', $_POST['public'], PDO::PARAM_STR);
        $result->execute();
        $event = $result->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            header('Location: ./index.php?&error=No public');
        }
        $insert = "INSERT INTO `guests` (`name`, `email`, `status`, `invite_key`, `approved`, `event_id`)
			VALUES (:name, :email, :status, :invite, '1', :event);";
        $result = $conn->prepare($insert);
        $result->bindParam(':name', $_POST['name'], PDO::PARAM_STR);
        $result->bindParam(':email', $_POST['email'], PDO::PARAM_STR);
        $result->bindParam(':status', $_POST['status'], PDO::PARAM_STR);
        $invite = makeToken(50);
        $result->bindParam(':invite', $invite, PDO::PARAM_STR);
        $result->bindParam(':event', $event['id'], PDO::PARAM_STR);
        $result->execute();
        header('Location: ./index.php?invite=' . $invite);
    }

    // ================USER PAGE================
    if (isset($_POST['invite_key'])) {
        $sql = "SELECT * FROM `guests` WHERE `invite_key` = :invite";
        $result = $conn->prepare($sql);
        $result->bindParam(':invite', $_POST['invite_key'], PDO::PARAM_STR);
        $result->execute();
        $guest = $result->fetch(PDO::FETCH_ASSOC);
        if (!$guest) {
            die(header('Location: ./index.php?&error=Not invited'));
        }
        $update = "UPDATE `guests` 
			SET `status` = :status
			WHERE `id` = :id";
        $result = $conn->prepare($update);
        $result->bindParam(':status', $_POST['status'], PDO::PARAM_STR);
        $result->bindParam(':id', $guest['id'], PDO::PARAM_STR);
        $result->execute();
        header('Location: ./index.php?invite=' . $_POST['invite_key']);
    }

    // ================USER INVITE================
    if (isset($_POST['invite'])) {
        $sql = "SELECT * FROM `guests` WHERE `invite_key` = :invite";
        $result = $conn->prepare($sql);
        $result->bindParam(':invite', $_POST['invite'], PDO::PARAM_STR);
        $result->execute();
        $guest = $result->fetch(PDO::FETCH_ASSOC);
        $sql = "SELECT * FROM `guests` WHERE `email` = :email";
        $result = $conn->prepare($sql);
        $result->bindParam(':email', $_POST['email'], PDO::PARAM_STR);
        $result->execute();
        $newinv = $result->fetch(PDO::FETCH_ASSOC);
        if (!$guest) {
            die(header('Location: ./index.php?&error=Not invited'));
        }
        if ($guest['can_invite'] == 0) {
            die(header('Location: ./index.php?&error=Can\'t invite'));
        }
        if ($newinv) {
            die(header('Location: ./index.php?&error=Already invited'));
        }
        $insert = "INSERT INTO `guests` (`name`, `email`, `status`, `invite_key`, `approved`, `event_id`)
			VALUES (:name, :email, 'Suggested Guest', :invite, '0', :event);";
        $result = $conn->prepare($insert);
        $result->bindParam(':name', $_POST['name'], PDO::PARAM_STR);
        $result->bindParam(':email', $_POST['email'], PDO::PARAM_STR);
        $invite = makeToken(50);
        $result->bindParam(':invite', $invite, PDO::PARAM_STR);
        $result->bindParam(':event', $guest['event_id'], PDO::PARAM_STR);
        $result->execute();
        header('Location: ./index.php?invite=' . $_POST['invite'] . "&success=" . $_POST['email']);
    }
}

// ================GET================
if ($_GET) {
    // ================ADMIN PAGE================
    if (isset($_GET['admin'])) {
        $sql = "SELECT * FROM `events` WHERE `admin_key` = :admin";
        $result = $conn->prepare($sql);
        $result->bindParam(':admin', $_GET['admin'], PDO::PARAM_STR);
        $result->execute();
        $event = $result->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            die(header('Location: ./index.php?&error=Not admin'));
        }
        $html .= '
	<div class="container">
		<div class="jumbotron">
			<h1 class="display-4">EZPZ RSVP</h1>
			<p class="lead">Hey ' . $event["host"] . '!</p>
			<p>Lets check out the details for <b>"' . $event["name"] . '</b>":<br>
			<i>' . nl2br($event["about"]) . '</i> <br>
			<a href="./index.php?edit=' . $_GET['admin'] . '">Edit Event Details</a><br>
			<a href="./index.php?public=' . $event["open_invite_key"] . '">Public invite URL</a></p>
			<hr class="my-4">
				<form method="POST" action="index.php">
				<div class="form-group">
					<h4>Invite New Guest</h4>
					<input type="hidden" name="admin_key" value="' . $_GET['admin'] . '" />
					Name: <input class="form-control" type="text" name="name" required autofocus/> 
					Email: (optional)<input class="form-control" type="email" name="email" /> 
				</div>
					<input class="btn btn-primary float-right" type="submit" value="Invite!" />
				</form><br>';
        $sql = "SELECT count(*) AS `count`, status FROM `guests` WHERE `event_id` = :event GROUP BY status";
        $counts = $conn->prepare($sql);
        $counts->bindParam(':event', $event['id'], PDO::PARAM_STR);
        $counts->execute();
        if ($counts) if ($counts->rowCount() > 0) {
            $totals = '-- ';
            while ($row = $counts->fetch(PDO::FETCH_ASSOC)) {
                $totals .= $row['status'] . ": " . $row['count'] . " -- ";
            }
        }
        $sql = "SELECT * FROM `guests` WHERE `event_id` = :event ORDER BY status";
        $result = $conn->prepare($sql);
        $result->bindParam(':event', $event['id'], PDO::PARAM_STR);
        $result->execute();
        if ($result) if ($result->rowCount() > 0) {
            $html .= "<br><h3>Guest list (" . $result->rowCount()    . " total):</h3><hr><small>" . $totals . "</small><br>";
            $status = '';
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                if ($row['status'] != $status) {
                    $html .= "<h4><i>" . $row['status'] . "</i></h4>";
                    $status = $row['status'];
                }
                //UNINVITE
                $uninvite = ' | <a class="text-danger" href="./index.php?admin_key=' . $_GET['admin'] . '&uninvite=' . $row['invite_key'] . '">Uninvite</a>';
                //APPROVE
                if ($row['approved'] == 0) {
                    $approved = ' -> <a class="text-success" href="./index.php?admin_key=' . $_GET['admin'] . '&approve=' . $row['invite_key'] . '">Approve Invite</a> | ';
                } else {
                    $approved = '';
                }
                //INVITE
                if ($row['can_invite'] == 0) {
                    $invite = ' | <a href="./index.php?admin_key=' . $_GET['admin'] . '&can_invite=' . $row['invite_key'] . '">Give Invite Permission</a>';
                } else {
                    $invite = ' | <a href="./index.php?admin_key=' . $_GET['admin'] . '&can_invite=' . $row['invite_key'] . '">Remove Invite Permission</a>';
                }
                $html .= "<li>" . $row['name'] . ($row['email'] ? " (" . $row['email'] . ")" : "") . ": " . $approved . " <a href='./index.php?invite=" . $row['invite_key'] . "'>Invite URL</a>" . $invite . $uninvite . "</li>";
            }
        }
        $html .= '
			<small>Note: Guests invited by other attendees must be approved by you before an email invite is sent.</small>
			</hr>
		</div>
	<div>
</body></html>';
    }
    // ================ADMIN EDIT DETAILS================
    if (isset($_GET['edit'])) {
        $sql = "SELECT * FROM `events` WHERE `admin_key` = :admin";
        $result = $conn->prepare($sql);
        $result->bindParam(':admin', $_GET['edit'], PDO::PARAM_STR);
        $result->execute();
        $event = $result->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            die(header('Location: ./index.php?&error=Not admin'));
        }
        $html .= '<div class="container">
		<div class="jumbotron">
			<h1 class="display-4">EZPZ RSVP</h1>
			<p class="lead">Edit Event Details</p>
			<hr class="my-4">
			<p class="lead">Guests who have been invited already will not be notified. <br>Updated information will be visible if they view their personalized invite URL or when using the public invite URL.</p>
				<form method="POST" action="index.php">
					<input type="hidden" name="edit_key" value="' . $_GET['edit'] . '" />
					<label for="eventName">Name of Event:</label> <input class="form-control" maxlength="100" type="text" id="eventName" name="editevent" value="' . $event["name"] . '" required autofocus/><br>
					<label for="eventHost">Event Host Name:</label> <input class="form-control" maxlength="100" type="text" id="eventHost" name="edithost" value="' . $event["host"] . '" required/><br>
					<label for="eventAbout">Description of Event: </label>
					<textarea maxlength="500" rows="4" class="form-control" type="text" id="eventAbout" name="editabout" required>' . $event["about"] . '</textarea><br>
					<input class="btn btn-primary float-right" type="submit" value="Update!" />
				</form>
			</hr>
		</div>
	</div>
</body></html>';
    }
    // ================USER PAGE================
    if (isset($_GET['invite'])) {
        $sql = "SELECT * FROM `guests` WHERE `invite_key` = :invite";
        $result = $conn->prepare($sql);
        $result->bindParam(':invite', $_GET['invite'], PDO::PARAM_STR);
        $result->execute();
        $guest = $result->fetch(PDO::FETCH_ASSOC);
        if (!$guest) {
            die(header('Location: index.php?&error=Not invited'));
        }
        $sql = "SELECT * FROM `events` WHERE `id` = :id";
        $result = $conn->prepare($sql);
        $result->bindParam(':id', $guest['event_id'], PDO::PARAM_STR);
        $result->execute();
        $event = $result->fetch(PDO::FETCH_ASSOC);
        $coming = $maybe = $nope = $invited = '';
        $status = ' You are currently listed as: ';
        if ($guest["status"] == 'Coming') {
            $coming = 'selected';
            $status .= $guest["status"];
        } elseif ($guest["status"] == 'Maybe Coming') {
            $maybe = 'selected';
            $status .= $guest["status"];
        } elseif ($guest["status"] == 'Not Coming') {
            $nope = 'selected';
            $status .= $guest["status"];
        } else {
            $invited = 'selected';
            $status = '';
        }

        if ($guest["can_invite"] == 1) {
            $success = '';
            if ($_GET['success']) {
                $success = '<b><u>' . $_GET['success'] . '</u> has been submitted to the organizer.</b><br>';
            }
            $inv = '<br><p class="lead">You also have been granted invite permission! If there is anyone you think should be invited, use the form below. </p>
				<hr class="my-4">
				<form method="POST" action="index.php">
				<div class="form-group">
					<input class="form-control" type="hidden" name="invite" value="' . $_GET['invite'] . '" />
					Name: <input class="form-control" type="text" name="name" autofocus/> 
					Email: <input class="form-control" type="email" name="email" required/> 
				</div>
					<input class="btn btn-primary float-right" type="submit" value="Invite!" />
				</form>' . $success . '<small>Note: The organizer will need to approve your choice(s).</small>';
        } else {
            $inv = '';
        }

        $html .= '
	<div class="container">
		<div class="jumbotron">
			<h1 class="display-4">' . $event["name"] . '</h1>
			<p class="lead">' . nl2br($event["about"]) . '</p>
			<small>Hosted by ' . $event["host"] . '</small>
			<hr class="my-4">
				<h3>Hey ' . $guest["name"] . '!</h3>
				<p>You have been invited!' . $status . '</p>
				<form method="POST" action="index.php">
					<input type="hidden" name="invite_key" value="' . $_GET['invite'] . '" />
					RSVP as: <select name="status">
						<option value="Invited" ' . $invited . ' hidden>Invited</option>
						<option value="Coming" ' . $coming . '>Coming</option>
						<option value="Maybe Coming" ' . $maybe . '>Maybe Coming</option>
						<option value="Not Coming" ' . $nope . '>Not Coming</option>
					</select> 
					<input class="button" type="submit" value="Save" />
				</form>' . $inv;
    }

    // ================PUBLIC INVITE================
    if (isset($_GET['public'])) {
        $sql = "SELECT * FROM `events` WHERE `open_invite_key` = :key";
        $result = $conn->prepare($sql);
        $result->bindParam(':key', $_GET['public'], PDO::PARAM_STR);
        $result->execute();
        $event = $result->fetch(PDO::FETCH_ASSOC);
        $html .= '
	<div class="container">
		<div class="jumbotron">
			<h1 class="display-4">' . $event["name"] . '</h1>
			<p class="lead">' . nl2br($event["about"]) . '</p>
			<small>Hosted by ' . $event["host"] . '</small>
			<hr class="my-4">
				<p>Enter your details to RSVP!</p>
				<form method="POST" action="index.php">
				<div class="form-group">
					<input type="hidden" name="public" value="' . $_GET['public'] . '" />
					Name: <input class="form-control" type="text" name="name" autofocus/> 
					Email: <input class="form-control" type="email" name="email" required/> 
					RSVP as: <select class="form-control" name="status">
						<option value="Invited" selected hidden>Select</option>
						<option value="Coming">Coming</option>
						<option value="Maybe Coming">Maybe Coming</option>
						<option value="Not Coming">Not Coming</option>
					</select> 
					</div>
					<input class="btn btn-primary float-right" type="submit" value="Save" />
					<br>
				</form>';
    }

    // ================UNINVITE================
    if (isset($_GET['uninvite'])) {
        $sql = "DELETE FROM `guests` WHERE `invite_key` = :invite";
        $result = $conn->prepare($sql);
        $result->bindParam(':invite', $_GET['uninvite'], PDO::PARAM_STR);
        $result->execute();
        header('Location: ./index.php?admin=' . $_GET['admin_key']);
    }

    // ================APPROVE GUEST INVITE================
    if (isset($_GET['approve'])) {
        $sql = "SELECT * FROM `guests` WHERE `invite_key` = :invite";
        $result = $conn->prepare($sql);
        $result->bindParam(':invite', $_GET['approve'], PDO::PARAM_STR);
        $result->execute();
        $guest = $result->fetch(PDO::FETCH_ASSOC);
        if (!$guest) {
            die(header('Location: ./index.php?&error=Not invited'));
        }
        $update = "UPDATE `guests` 
			SET `status` = 'Pending Email', `approved` = '1'
			WHERE `id` = :id";
        $result = $conn->prepare($update);
        $result->bindParam(':id', $guest['id'], PDO::PARAM_STR);
        $result->execute();
        header('Location: ./index.php?admin=' . $_GET['admin_key']);
    }

    // ================GRANT / DENY PERMISSION================
    if (isset($_GET['can_invite'])) {
        $sql = "SELECT * FROM `guests` WHERE `invite_key` = :invite";
        $result = $conn->prepare($sql);
        $result->bindParam(':invite', $_GET['can_invite'], PDO::PARAM_STR);
        $result->execute();
        $guest = $result->fetch(PDO::FETCH_ASSOC);
        if (!$guest) {
            die(header('Location: ./index.php?&error=Not invited'));
        }
        if ($guest['can_invite'] == 0) {
            $inv = 1;
        } else {
            $inv = 0;
        }
        $update = "UPDATE `guests` 
			SET `can_invite` = :inv
			WHERE `id` = :id";
        $result = $conn->prepare($update);
        $result->bindParam(':inv', $inv, PDO::PARAM_STR);
        $result->bindParam(':id', $guest['id'], PDO::PARAM_STR);
        $result->execute();
        header('Location: ./index.php?admin=' . $_GET['admin_key']);
    }
}

// ================CREATE EVENT================
if (!$_GET && !$_POST) {
    $html .= '
	<div class="container">
		<div class="jumbotron">
			<h1 class="display-4">EZPZ RSVP</h1>
			<p class="lead">The simple, flexible way to organize a guest list, even with friends who don\'t use social media. 
			<br>No sign up, no ads, free to use!</p>
			<p>->Let us send out an email invite or maintain your guests privacy and share their unique invite URL yourself, in any way you want. 
			<br>The choice is yours!</p>
			<p>->Give trusted guests the ability to suggest new attendees! Once a suggestion is approved by you, we can send them an email invite, 
			<br>or as always you can share their invite URL.</p>
			<p>->Your unique public signup URL can be shared so anyone can add themselves to the event guest list.</p>
			<p>Try it yourself! Create a new event below.</p>
			<hr class="my-4">
				<form method="POST" action="index.php">
					<label for="eventName">Name of Event:</label> <input class="form-control" maxlength="100" type="text" id="eventName" name="event" required autofocus/><br>
					<label for="eventHost">Event Host Name:</label> <input class="form-control" maxlength="100" type="text" id="eventHost" name="host" required/><br>
					<label for="eventAbout">Description of Event: </label>
					<textarea maxlength="500" rows="4" class="form-control" type="text" id="eventAbout" name="about" required></textarea><br>
					<input class="btn btn-primary float-right" type="submit" value="Create!" />
				</form>
			</hr>
		</div>
	</div>
</body></html>';
}
echo $html;


$sql = "SELECT * FROM `guests` WHERE `approved` = 1 AND `status` = 'Pending Email' ORDER BY RAND() LIMIT 1";
$result = $conn->prepare($sql);
$result->execute();
$pending = $result->fetch(PDO::FETCH_ASSOC);
if ($pending) {
    $sql = "SELECT * FROM `events` WHERE `id` = :id";
    $result = $conn->prepare($sql);
    $result->bindParam(':id', $pending['event_id'], PDO::PARAM_STR);
    $result->execute();
    $event = $result->fetch(PDO::FETCH_ASSOC);
    $message = '<!DOCTYPE html>
	<html>
	<h1>' . $event['name'] . '</h1><p><i>' . nl2br($event['about']) . '</i><p>
	<p>Hey ' . $pending['name'] . ',
	<br>' . $event['host'] . ' has invited you and would love an RSVP. 
	<br>Please visit https://nervesocket.com/rsvp/?invite=' . $pending['invite_key'] . ' and update your attendance.
	<br>It will only take a few seconds.</p>
	Thank you!
	</html>';
    $success = emailCRON("You're invited to " . $event['name'], $message, $pending['email']);
    if ($success) {
        $update = "UPDATE `guests` 
			SET `status` = 'Invited'
			WHERE `id` = :id";
        $result = $conn->prepare($update);
        $result->bindParam(':id', $pending['id'], PDO::PARAM_STR);
        $result->execute();
    }
}

//=====================================================
//=====================================================
function makeToken($size)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $string = '';
    $max = strlen($characters) - 1;
    for ($i = 0; $i < $size; $i++) {
        $string .= $characters[mt_rand(0, $max)];
    }
    return $string;
}

function emailCRON($subject, $message, $to)
{
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Event Invite<admin@nervesocket.com>\r\n";

    //$to = 'sir.mike.johnston@gmail.com';

    //$notice = urlencode('RSVP email sent to: '.$to);
    //$notify = file_get_contents("http://nervesocket.com/irc/ircq.php?q=".$notice);

    if (mail($to, $subject, $message, $headers)) {
        return true;
    } else {
        return false;
    }
}
