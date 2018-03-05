<?php

require dirname(__FILE__) . '/../vendor/autoload.php';

/*
	If you are not using composer, use the following file to load all the class files instead of composer's autoload.php above
	require dirname(__FILE__) . '/../lib/bambora_payform_loader.php';
*/

$payForm = new Bambora\Payform('api_key', 'private_key');

if(isset($_GET['action']))
{
	if($_GET['action'] == 'auth-payment')
	{
		$serverPort = (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] != 80 &&  $_SERVER['SERVER_PORT'] != 433)) ? ':' . $_SERVER['SERVER_PORT'] : ''; 

		$returnUrl = strstr("http" . (!empty($_SERVER['HTTPS']) ? "s" : "") . "://" . $_SERVER['SERVER_NAME'] . $serverPort . $_SERVER['REQUEST_URI'], '?', true)."?return-from-pay-page";

		$method = isset($_GET['method']) ? $_GET['method'] : '';

		$payForm->addCharge(array(
			'order_number' => 'example_payment_' . time(),
			'amount' => 2000,
			'currency' => 'EUR'
		));

		$payForm->addCustomer(array(
			'firstname' => 'Example', 
			'lastname' => 'Testaaja', 
			'address_street' => 'Testaddress 1',
			'address_city' => 'Testlandia',
			'address_zip' => '12345'
		));

		$payForm->addProduct(array(
			'id' => 'product-id-123', 
			'title' => 'Product 1',
			'count' => 1,
			'pretax_price' => 2000,
			'tax' => 1,
			'price' => 2000,
			'type' => 1
		));

		if($method === 'card-payment')
		{
			$paymentMethod = array(
				'type' => 'card', 
				'register_card_token' => 0
			);
		}
		else
		{
			$paymentMethod = array(
				'type' => 'e-payment', 
				'return_url' => $returnUrl,
				'notify_url' => $returnUrl,
				'lang' => 'fi'
			);

			if(isset($_GET['selected']))
			{
				$paymentMethod['selected'] = array(strip_tags($_GET['selected']));
			}
		}

		$payForm->addPaymentMethod($paymentMethod);

		try 
		{
			$result = $payForm->createCharge();

			if($result->result == 0)
			{
				if($method === 'card-payment')
				{
					echo json_encode(array(
						'token' => $result->token,
						'url' => $payForm::API_URL . '/charge'
					));
				}
				else
				{
					header('Location: ' . $payForm::API_URL . '/token/' . $result->token);
				}
			}
			else
			{
				$error_msg = 'Unable to create a payment. ';

				if(isset($result->errors) && !empty($result->errors))
				{
					$error_msg .= 'Validation errors: ' . print_r($result->errors, true);
				}
				else
				{
					$error_msg .= 'Please check that api key and private key are correct.';
				}

				exit($error_msg);
			}
		}
		catch(Bambora\PayformException $e)
		{
			exit('Got the following exception: ' . $e->getMessage());
		}
	}
	else if($_GET['action'] === 'check-payment-status')
	{
		try
		{
			$result = $payForm->checkStatusWithToken($_GET['token']);

			echo $result->result == 0 ? 'success' : 'failed';
		}
		catch(Bambora\PayformException $e)
		{
			exit('Got the following exception: ' . $e->getMessage());
		}
	}

	exit();
}
else if(isset($_GET['return-from-pay-page']))
{
	try
	{
		$result = $payForm->checkReturn($_GET);

		if($result->RETURN_CODE == 0)
		{
			exit('Payment succeeded, <a href="index.php">start again</a>');	
		}
		else
		{
			exit('Payment failed (RETURN_CODE: ' . $result->RETURN_CODE . '), <a href="index.php">start again</a>');
		}
	}
	catch(Bambora\PayformException $e)
	{
		exit('Got the following exception: ' . $e->getMessage());
	}
}

try
{
	$merchantPaymentMethods = $payForm->getMerchantPaymentMethods();

	if($merchantPaymentMethods->result != 0)
	{
		exit('Unable to get the payment methods for the merchant. Please check that api key and private key are correct.');
	}
}
catch(Bambora\PayformException $e)
{
	exit('Got the following exception: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>PayForm PHP Library Example</title>
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
		<style type="text/css">
			a, a:hover, a:focus
			{
				text-decoration: none;
			}
		</style>	
	</head>
	<body>
		<div class="container">
			<div class="row">
				<div class="col-md-12">
					<h1>PayForm PHP Library Example</h1>

					<h2>Embedded Card Payment</h2>

					<form id="card-form" action="#" role="form" autocomplete="off">
						<div class="form-group">
							<label for="cardNumber">Card number</label>
							<input type="number" id="cardNumber" lenght="30" placeholder="Enter the card number" class="form-control"/>
						</div>
						<div class="row">
							<div class="col-xs-6">
								<div class="form-group">
									<label for="expMonth">Month</label>
									<select id="expMonth" class="form-control card-exp-month">
										<?php
										for($i = 1; $i <= 12; $i++)
											echo "<option>".str_pad($i,2,'0',STR_PAD_LEFT)."</option>";
										?>
									</select>
								</div>
							</div>
							<div class="col-xs-6">
								<div class="form-group">
									<label for="expYear">Year</label>
									<select id="expYear" class="form-control card-exp-year">
									<?php
									$i = $j = date("Y");
									while($i <= $j + 5)
										echo "<option>".$i++."</option>";
									?>
									</select>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-xs-6">
								<div class="form-group">
									<label for="cvv">CVV</label>
									<input type="number" id="cvv" maxlength="4" class="form-control" lenght="4" placeholder="Enter the CVV"/>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-xs-12">
								<div class="form-group">
									<input type="submit" id="card-pay" class="btn btn-primary" value="Pay"/>
								</div>
							</div>
						</div>
					</form>
					<div class="card-payment-result text-muted"></div>
					<hr>
					<h2>Pay by button</h2>
					<?php foreach ($merchantPaymentMethods->payment_methods as $pm): ?>
						<a class="img" href="?action=auth-payment&method=button&selected=<?=$pm->selected_value?>">
							<img alt="<?= $pm->name ?>" src="<?= $pm->img ?>">
						</a>
					<?php endforeach; ?>
					<hr>
					<h2>Go to pay page</h2>
					<a class="btn btn-default" href="index.php?action=auth-payment&method=button">Go to pay page</a>
				</div>
			</div>
		</div>
	<script>
		var card_payment_result = $('.card-payment-result')

		$('#card-form').submit(function(e) {
			e.preventDefault()

			card_payment_result.html('')
			card_payment_result.append('Getting token...')

			var chargeRequest = $.get("?action=auth-payment&method=card-payment")

			chargeRequest.done(function(data) {

				card_payment_result.append('<br>Charging the card...')

				var response

				try
				{
					response = $.parseJSON(data)
				}
				catch(err)
				{
					card_payment_result.html('Unable to create card payment. Please check that api key and private key are correct.')
					alert('Unable to create card payment. Please check that api key and private key are correct.')
					return
				}

				var charge = $.post(response.url, {
					token: response.token,
					amount: 2000,
					card: $('#cardNumber').val(),
					security_code: $('#cvv').val(),
					currency: 'EUR',
					exp_month: $('#expMonth').val(),
					exp_year: $('#expYear').val()
				})

				charge.done(function(result) {	
					card_payment_result.append('<br>Checking the payment status...')
					var complete = $.get('?action=check-payment-status&token=' + response.token)

					complete.done(function(data) {
						var msg = (data === 'success') ? '<strong class="text-success">Payment was successful!</strong>' : '<strong class="text-danger">Payment failed!</strong>'
						card_payment_result.append('<br>' + msg)
					})
				})
			})
		})
	</script>
	</body>
</html>
