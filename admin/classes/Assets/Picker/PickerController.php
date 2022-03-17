<?php
	namespace Assets\Picker;
	
	use Admin\AdminController;
	use Controller\JsonController;
	use Controller\UrlController;
	use Exception;
	use Files\File;
	use Files\FileSystemItem;
	use Files\Folder;
	use Files\Image;
	use ResizedImage;
	
	/**
	 * Handles picker actions
	 */
	class PickerController extends AdminController
	{
		private $type;
		
		const FILES_ROOT = DOC_ROOT . "/resources/files/picker";
		const IMAGES_ROOT = DOC_ROOT . "/resources/images/picker";

		const MAX_THUMB_WIDTH = 125;
		const MAX_THUMB_HEIGHT = 125;
		
		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return    UrlController[]|string[]    Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		protected static function getChildPatterns()
		{
			return
			[
				'/output/' => self::class,
				'/folder/create/' => self::class,
				'/folder/rename/' => self::class,
				'/folder/move/' => self::class,
				'/folder/delete/' => self::class,
				'/file/upload/' => self::class,
				'/file/rename/' => self::class,
				'/file/move/' => self::class,
				'/file/modify/' => self::class,
				'/file/delete/' => self::class
			];
		}

		/**
		 * Gets the JSON for a file system item
		 * @param	FileSystemItem|null		$item	The item to get the JSON for
		 * @param	string					$type	The type of item that we're currently looking at
		 * @return	array							The JSON data
		 */
		private static function getJson(?FileSystemItem $item, string $type): ?array
		{
			if($item === null)
			{
				return null;
			}
			
			$data =
			[
				"path" => $item->getLink(),
				"filename" => $item->getFilename(),
				"type" => $item instanceof File ? "file" : "folder"
			];

			if($item instanceof File && $type === "image")
			{
				$data["thumb"] = ResizedImage::scale(new Image($item->path), static::MAX_THUMB_WIDTH, static::MAX_THUMB_HEIGHT)->getLink();
			}

			return $data;
		}
		
		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param	UrlController|null	$parent		The parent to the Page Child Controller
		 * @param	string[]			$matches	An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param	string				$pattern	The pattern that was matched
		 * @return	UrlController				An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			// Disable xdebug, so it won't break our JSON output
			if(function_exists("xdebug_disable"))
			{
				// Call xdebug_disable indirectly, so it's not required for our tests
				call_user_func("xdebug_disable");
			}
			
			if(!$parent instanceof PickerController)
			{
				if($matches["type"] !== "image" && $matches["type"] !== "file")
				{
					return null;
				}
				
				return new self($matches["type"]);
			}
			
			$rootPath = ($parent->type === "image" ? static::IMAGES_ROOT : static::FILES_ROOT) . "/";
			$picker = new Picker($rootPath);

			try
			{
				switch($pattern)
				{
					case '/output/':
						$folderPath = isset($_GET["folder"]) ? DOC_ROOT . $_GET["folder"] : $rootPath;
						$folder = new Folder($folderPath);
						$folder = $picker->getOutputFolder($folder);

						$callback = function(FileSystemItem $item) use($parent)
						{
							return self::getJson($item, $parent->type);
						};

						$output =
						[
							"path" => $folder->getLink(),
							"parent" => self::getJson($picker->getParentFolder($folder), $parent->type),
							"folders" => array_map($callback, $picker->getChildFolders($folder)),
							"files" => array_map($callback, $picker->getFiles($folder))
						];

						return new JsonController($output);

					case '/folder/create/':
						if(isset($_POST["parent"], $_POST["filename"]))
						{
							$parentFolder = new Folder(DOC_ROOT . $_POST["parent"]);
							$newFolder = $picker->createNewFolder($parentFolder, $_POST["filename"]);
							return new JsonController(self::getJson($newFolder, $parent->type));
						}
					break;

					case '/folder/rename/':
						if(isset($_POST["folder"], $_POST["filename"]))
						{
							$folder = new Folder(DOC_ROOT . $_POST["folder"]);
							$picker->renameFolder($folder, $_POST["filename"]);
							return new JsonController(self::getJson($folder, $parent->type));
						}
					break;

					case '/folder/move/':
						if(isset($_POST["folder"], $_POST["parent"]))
						{
							$folder = new Folder(DOC_ROOT . $_POST["folder"]);
							$newParent = new Folder(DOC_ROOT . $_POST["parent"]);
							$picker->moveFolder($folder, $newParent);
							return new JsonController(self::getJson($folder, $parent->type));
						}
					break;

					case '/folder/delete/':
						if(isset($_POST["folder"]))
						{
							$folder = new Folder(DOC_ROOT . $_POST["folder"]);
							$picker->deleteFolder($folder);
							return new JsonController(["success" => true]);
						}
					break;

					case '/file/upload/':
						if(isset($_FILES["file"]["tmp_name"], $_POST["folder"]))
						{
							$folder = new Folder(DOC_ROOT . $_POST["folder"]);
							$file = new File($_FILES["file"]["tmp_name"]);
							$file->originalName = $_FILES["file"]["name"];
							$picker->insertFile($file, $folder);
							return new JsonController(self::getJson($file, $parent->type));
						}
					break;

					case '/file/rename/':
						if(isset($_POST["file"], $_POST["filename"]))
						{
							$file = new File(DOC_ROOT . $_POST["file"]);
							$picker->renameFile($file, $_POST["filename"]);
							return new JsonController(self::getJson($file, $parent->type));
						}
					break;

					case '/file/move/':
						if(isset($_POST["file"], $_POST["folder"]))
						{
							$folder = new Folder(DOC_ROOT . $_POST["folder"]);
							$file = new File(DOC_ROOT . $_POST["file"]);
							$picker->moveFile($file, $folder);
							return new JsonController(self::getJson($file, $parent->type));
						}
					break;

					case '/file/modify/':
						if(isset($_FILES["file"]["tmp_name"]))
						{
							$folder = new Folder($rootPath);
							$file = new File($_FILES["file"]["tmp_name"]);
							$file->originalName = $_FILES["file"]["name"];
							$image = $picker->insertOrReplaceImage($file, $folder);
							return new JsonController(["location" => $image->getLink()]);
						}
					break;

					case '/file/delete/':
						if(isset($_POST["file"]))
						{
							$file = new File(DOC_ROOT . $_POST["file"]);
							$picker->deleteFile($file);
							return new JsonController(["success" => true]);
						}
					break;
				}
			}
			catch(Exception $exception)
			{
				return new JsonController(["error" => $exception->getMessage()]);
			}
			
			return null;
		}
		
		public function __construct(string $type)
		{
			$this->type = $type;
		}
		
		/**
		 * Retrieves the location of the template to display to the user
		 * @return	string	The location of the template
		 */
		protected function getTemplateLocation()
		{
			// Unused
			return "";
		}
	}