Feature: File search replace command scaffolding

	Scenario: Handler scaffold exists
		When I run `php -r 'echo file_exists("src/PhpSearchReplaceHandler.php") ? "ok" : "fail";'`
		Then STDOUT should be:
		  """
		  ok
		  """
