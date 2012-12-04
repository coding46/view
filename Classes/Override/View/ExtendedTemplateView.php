<?php
namespace TYPO3\CMS\View\Override\View;

class ExtendedTemplateView extends \TYPO3\CMS\Fluid\View\TemplateView implements \TYPO3\CMS\Extbase\Mvc\View\ViewInterface {

	/**
	 * Processes "@templateRoot", "@subpackage", "@controller", and "@format" placeholders inside $pattern.
	 * This method is used to generate "fallback chains" for file system locations where a certain Partial can reside.
	 *
	 * If $bubbleControllerAndSubpackage is FALSE and $formatIsOptional is FALSE, then the resulting array will only have one element
	 * with all the above placeholders replaced.
	 *
	 * If you set $bubbleControllerAndSubpackage to TRUE, then you will get an array with potentially many elements:
	 * The first element of the array is like above. The second element has the @ controller part set to "" (the empty string)
	 * The third element now has the @ controller part again stripped off, and has the last subpackage part stripped off as well.
	 * This continues until both "@subpackage" and "@controller" are empty.
	 *
	 * Example for $bubbleControllerAndSubpackage is TRUE, we have the Tx_MyExtension_MySubPackage_Controller_MyController
	 * as Controller Object Name and the current format is "html"
	 *
	 * If pattern is "@templateRoot/@subpackage/@controller/@action.@format", then the resulting array is:
	 * - "Resources/Private/Templates/MySubPackage/My/@action.html"
	 * - "Resources/Private/Templates/MySubPackage/@action.html"
	 * - "Resources/Private/Templates/@action.html"
	 *
	 * If you set $formatIsOptional to TRUE, then for any of the above arrays, every element will be duplicated  - once with "@format"
	 * replaced by the current request format, and once with ."@format" stripped off.
	 *
	 * @param string $pattern Pattern to be resolved
	 * @param boolean $bubbleControllerAndSubpackage if TRUE, then we successively split off parts from "@controller" and "@subpackage" until both are empty.
	 * @param boolean $formatIsOptional if TRUE, then half of the resulting strings will have ."@format" stripped off, and the other half will have it.
	 * @return array unix style path
	 */
	protected function expandGenericPathPattern($pattern, $bubbleControllerAndSubpackage, $formatIsOptional) {
		$subpackageKey = $this->controllerContext->getRequest()->getControllerSubpackageKey();
		$pathOverlayConfigurations = $this->buildPathOverlayConfigurations($subpackageKey);
		$templateRootPath = $this->getTemplateRootPath();
		$partialRootPath = $this->getPartialRootPath();
		$layoutRootPath = $this->getLayoutRootPath();
		$subpackageKey = $this->controllerContext->getRequest()->getControllerSubpackageKey();
		$paths = $this->expandGenericPathPatternWithCustomPaths($pattern, $bubbleControllerAndSubpackage, $formatIsOptional, $templateRootPath, $partialRootPath, $layoutRootPath);
		foreach ($pathOverlayConfigurations as $overlayPaths) {
			list ($templateRootPath, $partialRootPath, $layoutRootPath) = array_values($overlayPaths);
			$subset = $this->expandGenericPathPatternWithCustomPaths($pattern, $bubbleControllerAndSubpackage, $formatIsOptional, $templateRootPath, $partialRootPath, $layoutRootPath);
			$paths = array_merge($paths, $subset);
		}
		$paths = array_reverse($paths);
		return $paths;
	}

	/**
	 * @param string $subpackageKey
	 * @return array
	 */
	private function buildPathOverlayConfigurations($subpackageKey) {
		$configurations = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Configuration\\ConfigurationManagerInterface')
			->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK);
		$configurations = $configurations['view'];
		$templateRootPath = $this->getTemplateRootPath();
		$partialRootPath = $this->getPartialRootPath();
		$layoutRootPath = $this->getLayoutRootPath();
		$overlays = NULL;
		$paths = array();
		if (isset($configurations['overlays']) === TRUE) {
			$overlays = $configurations['overlays'];
			foreach ($overlays as $overlaySubpackageKey => $configuration) {
				if (isset($configuration['templateRootPath'])  === TRUE) {
					$templateRootPath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($configuration['templateRootPath']);
				}
				if (isset($configuration['partialRootPath']) === TRUE) {
					$partialRootPath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($configuration['partialRootPath']);
				}
				if (isset($configuration['layoutRootPath']) === TRUE) {
					$layoutRootPath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($configuration['layoutRootPath']);
				}
				$paths[$overlaySubpackageKey] = array(
					'templateRootPath' => $templateRootPath,
					'partialRootPath' => $partialRootPath,
					'layoutRootPath' => $partialRootPath
				);
			}
		}
		$paths = array_reverse($paths);
		$paths[] = array(
			'templateRootPath' => $templateRootPath,
			'partialRootPath' => $partialRootPath,
			'layoutRootPath' => $partialRootPath
		);
		return $paths;
	}

	/**
	 * @param string $pattern Pattern to be resolved
	 * @param boolean $bubbleControllerAndSubpackage if TRUE, then we successively split off parts from "@controller" and "@subpackage" until both are empty.
	 * @param boolean $formatIsOptional if TRUE, then half of the resulting strings will have ."@format" stripped off, and the other half will have it.
	 * @param string $templateRootPath
	 * @param string $partialRootPath
	 * @param string $layoutRootPath
	 * @return array
	 */
	private function expandGenericPathPatternWithCustomPaths($pattern, $bubbleControllerAndSubpackage, $formatIsOptional, $templateRootPath, $partialRootPath, $layoutRootPath) {
		$pattern = str_replace('@templateRoot', $templateRootPath, $pattern);
		$pattern = str_replace('@partialRoot', $partialRootPath, $pattern);
		$pattern = str_replace('@layoutRoot', $layoutRootPath, $pattern);
		$controllerName = $this->controllerContext->getRequest()->getControllerName();
		$subpackageKey = $this->controllerContext->getRequest()->getControllerSubpackageKey();
		if ($subpackageKey !== NULL) {
			if (strpos($subpackageKey, \TYPO3\CMS\Fluid\Fluid::NAMESPACE_SEPARATOR) !== FALSE) {
				$namespaceSeparator = \TYPO3\CMS\Fluid\Fluid::NAMESPACE_SEPARATOR;
			} else {
				$namespaceSeparator = \TYPO3\CMS\Fluid\Fluid::LEGACY_NAMESPACE_SEPARATOR;
			}
			$subpackageParts = explode($namespaceSeparator, $subpackageKey);
		} else {
			$subpackageParts = array();
		}
		$results = array();
		$i = $controllerName === NULL ? 0 : -1;
		do {
			$temporaryPattern = $pattern;
			if ($i < 0) {
				$temporaryPattern = str_replace('@controller', $controllerName, $temporaryPattern);
			} else {
				$temporaryPattern = str_replace('//', '/', str_replace('@controller', '', $temporaryPattern));
			}
			$temporaryPattern = str_replace('@subpackage', implode('/', $i < 0 ? $subpackageParts : array_slice($subpackageParts, $i)), $temporaryPattern);
			$results[] = \TYPO3\CMS\Core\Utility\GeneralUtility::fixWindowsFilePath(str_replace('@format', $this->controllerContext->getRequest()->getFormat(), $temporaryPattern));
			if ($formatIsOptional) {
				$results[] = \TYPO3\CMS\Core\Utility\GeneralUtility::fixWindowsFilePath(str_replace('.@format', '', $temporaryPattern));
			}
		} while ($i++ < count($subpackageParts) && $bubbleControllerAndSubpackage);
		return $results;
	}

}