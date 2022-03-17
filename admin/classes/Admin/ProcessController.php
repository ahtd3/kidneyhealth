<?php
	namespace Admin;

	use Controller\JsonController;
	use Controller\RedirectController;
	use Controller\UrlController;
	use Core\Elements\Base\ResultElement;
	use Core\Entity;
	use Core\Exportable;
	use Core\Generator;
	use Database\Database;
	use Exception;
	use Files\File;
	use Users\User;
	
	/**
	 * Handles admin processes
	 */
	class ProcessController extends AdminController
	{
		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param    UrlController $parent  The parent to the Page Child Controller
		 * @param    string[]      $matches An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param    string        $pattern The pattern that was matched
		 * @return    UrlController                        An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			switch($pattern)
			{
				case '/action/new/$class/':
				case '/action/edit/$class/$id/':
					$class = Generator::getActualClassName($matches["class"]);
					assert(is_string($class) || $class instanceof Generator);

					if(isset($matches["id"]))
					{
						$generator = $class::load($matches["id"]);

						if($generator->isNull())
						{
							$singular = $generator::SINGULAR;
							return new JsonController(["success" => false, "errors" => ["{$singular} not found in database, have you already deleted it?"]]);
						}
					}
					else
					{
						$generator = new $class;
					}

					$json = json_decode($_POST["json"], true);
					$results = [];
					$errors = [];

					try
					{
						foreach($generator->getElements() as $element)
						{
							if($element instanceof ResultElement && array_key_exists($element->name, $json))
							{
								$result = $element->getResult($json[$element->name]);
								$results[$element->name] = $result;
								$errors = array_merge($errors, $element->validate($result));
							}
						}

						if(count($errors) === 0)
						{
							foreach($generator->getElements() as $element)
							{
								if($element instanceof ResultElement && array_key_exists($element->name, $results))
								{
									$element->handleResult($results[$element->name]);
								}
							}

							if(isset($_POST["active"]))
							{
								$generator->active = $_POST["active"] === "true";
							}

							$generator->save();
						}
						else
						{
							return new JsonController(
							[
								"success" => false,
								"errors" => $errors
							]);
						}
					}
					catch(Exception $exception)
					{
						return new JsonController(
						[
							"success" => false,
							"errors" => [$exception->getMessage()]
						]);
					}

					$url = null;
					$addAnother = ($json["addAnother"] ?? false) === true;
					$currentEditUrl = $generator->getEditLink();
					$newEditUrl = $generator->getEditLink($generator->getClassNameFromDatabase());

					if(isset($matches["id"]) && $addAnother)
					{
						$url = "/admin/{$matches["class"]}/new/";

						if($class::PARENT_PROPERTY !== null)
						{
							$id = $generator->{$class::PARENT_PROPERTY}->id;

							if($id !== null)
							{
								$url .= "{$id}/";
							}
						}
					}
					else if(!isset($matches["id"]) && !$addAnother)
					{
						$url = $newEditUrl;
					}
					else if($currentEditUrl !== $newEditUrl)
					{
						$url = $newEditUrl;
					}

					$customUrl = $generator->getCustomRedirect($addAnother);

					if($customUrl !== null)
					{
						$url = $customUrl;
					}

					return new JsonController(
					[
						"success" => true,
						"url" => $url
					]);

				break;

				case '/action/delete/$class/$id/':
					$class = Generator::getActualClassName($matches["class"]);
					assert(is_a($class, Generator::class, true));

					$id = $matches["id"];
					$generator = $class::load($id);

					assert($generator instanceof Generator);

					if(!$generator->canDelete(User::get()))
					{
						$singular = $generator::SINGULAR;
						return new JsonController(["success" => false, "error" => "You do not have permission to delete this {$singular}"]);
					}

					$generator->removeForUser(User::get(), (int) $id);

					return new JsonController(["success" => true]);
				break;
				
				case '/action/export/$class/':
					$class = Generator::getActualClassName($matches["class"]);

					if(!is_a($class, Exportable::class, true))
					{
						addMessage("$class does not implement Exportable");
						return new RedirectController($class::getAdminNavLink());
					}
					// else

					$class::export($_GET['filter'] ?? null, $_GET['format'] ?? null);
					// export methods should exit after streaming the file, but just in case
					exit;
				break;

				case '/action/reorder/':
					$data = json_decode($_POST["json"], true);

					Database::beginTransaction();

					foreach($data as $item)
					{
						$class = Generator::getActualClassName($item["class"]);
						assert(is_string($class) || $class instanceof Entity);
						$entity = $class::load($item["id"]);
						$entity->position = $item["position"];
						$entity->save();
					}

					Database::commitTransaction();

					return new JsonController(["success" => true]);
				break;

				case '/action/switch/$class/$id/$property/':
					$class = Generator::getActualClassName($matches["class"]);
					assert(is_string($class) || $class instanceof Entity);
					$property = $matches["property"];

					$entity = $class::load($matches["id"]);
					$entity->$property = !$entity->$property;
					$entity->save();

					return new JsonController(
					[
						"success" => true,
						"selected" => $entity->$property
					]);
				break;
				
				case '/action/update-property/$class/$id/$property/':
					$class = Generator::getActualClassName($matches["class"]);
					assert(is_string($class) || $class instanceof Entity);
					$property = $matches["property"];

					$entity = $class::load($matches["id"]);

					if(!$entity->canEdit(User::get()))
					{
						$niceName = $class::SINGULAR;
						return new JsonController(["success" => false, "error" => "You do not have permission to modify {$niceName}'s {$property}"]);
					}

					$entity->setFromElement($property, $_POST["value"]);
					$entity->save();

					return new JsonController(
					[
						"success" => true,
						"newValue" => $entity->$property
					]);
				break;

				case '/action/upload/':
					$_SESSION["temporaryUploads"] = $_FILES + ($_SESSION["temporaryUploads"] ?? []);

					// Delete any temporary uploads that have been missed
					foreach(scandir(File::TEMPORARY_UPLOADS_LOCATION) as $filename)
					{
						if($filename[0] === ".")
						{
							continue;
						}

						$path = File::TEMPORARY_UPLOADS_LOCATION . $filename;

						if(filemtime($path) < strtotime("-1 day"))
						{
							unlink($path);
						}
					}

					foreach($_FILES as $key => $fileData)
					{
						$file = new File($fileData["tmp_name"]);
						$file->move(File::TEMPORARY_UPLOADS_LOCATION);
					}

					return new JsonController(["success" => true]);
				break;
			}

			return null;
		}

		/**
		 * Retrieves the location of the template to display to the user
		 * @return    string    The location of the template
		 */
		protected function getTemplateLocation()
		{
			// Unused
			return "";
		}
	}