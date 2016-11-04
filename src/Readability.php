<?php

namespace andreskrey\Readability;

use League\HTMLToMarkdown\Element;

/**
 * Class DOMElement.
 *
 * This is a extension of the original Element class from League\HTMLToMarkdown\Element.
 * This class adds functions specific to Readability.php and overloads some of them to fit the purpose of this project.
 */
class Readability extends Element implements ReadabilityInterface
{
    /**
     * @var \DOMNode
     */
    protected $node;

    /**
     * @var int
     */
    protected $contentScore = 0;

    /**
     * @var array
     */
    private $regexps = [
        'positive' => '/article|body|content|entry|hentry|h-entry|main|page|pagination|post|text|blog|story/i',
        'negative' => '/hidden|^hid$| hid$| hid |^hid |banner|combx|comment|com-|contact|foot|footer|footnote|masthead|media|meta|modal|outbrain|promo|related|scroll|share|shoutbox|sidebar|skyscraper|sponsor|shopping|tags|tool|widget/i',
    ];

    /**
     * Constructor.
     *
     * @param \DOMNode $node Selected element from DOMDocument
     */
    public function __construct(\DOMNode $node)
    {
        parent::__construct($node);
    }

    /**
     * Checks for the tag name. Case insensitive.
     *
     * @param string $value Name to compare to the current tag
     *
     * @return bool
     */
    public function tagNameEqualsTo($value)
    {
        $tagName = $this->getTagName();
        if (strtolower($value) === strtolower($tagName)) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the current node has a single child and if that child is a P node.
     * Useful to convert <div><p> nodes to a single <p> node and avoid confusing the scoring system since div with p
     * tags are, in practice, paragraphs.
     *
     * @return bool
     */
    public function hasSinglePNode()
    {
        if ($this->hasChildren()) {
            $children = $this->getChildren();

            if (count($children) === 1) {
                if (strtolower($children[0]->getTagName()) === 'p') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the ancestors of the current node.
     *
     * @param int $maxLevel Max amount of ancestors to get.
     *
     * @return array
     */
    public function getNodeAncestors($maxLevel = 3)
    {
        $ancestors = [];
        $level = 0;

        $node = $this;

        while ($node && $node->getParent()) {
            $ancestors[] = new static($node->node);
            $level++;
            if ($level >= $maxLevel) {
                break;
            }
            $node = $node->getParent();
        }

        return $ancestors;
    }

    /**
     * Overloading the getParent function from League\HTMLToMarkdown\Element due to a bug when there are no more parents
     * on the selected element.
     *
     * @return Readability|null
     */
    public function getParent()
    {
        $node = $this->node->parentNode;

        return ($node) ? new self($node) : null;
    }

    /**
     * Returns all links from the current element.
     *
     * @return array|null
     */
    public function getAllLinks()
    {
        if (($this->isText())) {
            return null;
        } else {
            $links = [];
            foreach ($this->node->getElementsByTagName('a') as $link) {
                $links[] = new Readability($link);
            };
            return $links;
        }
    }

    /**
     * Initializer. Calculates the current score of the node and returns a full Readability object.
     *
     * @return Readability
     */
    public function initializeNode()
    {
        switch ($this->getTagName()) {
            case 'div':
                $this->contentScore += 5;
                break;

            case 'pre':
            case 'td':
            case 'blockquote':
                $this->contentScore += 3;
                break;

            case 'address':
            case 'ol':
            case 'ul':
            case 'dl':
            case 'dd':
            case 'dt':
            case 'li':
            case 'form':
                $this->contentScore -= 3;
                break;

            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
            case 'th':
                $this->contentScore -= 5;
                break;
        }

        $this->contentScore += $this->getClassWeight();

        return $this;
    }

    /**
     * Calculates the weight of the class/id of the current element.
     *
     * @todo check for flag that lets this function run or not
     *
     * @return int
     */
    public function getClassWeight()
    {
        // if(!Config::FLAG_WEIGHT_CLASSES) return 0;

        $weight = 0;

        // Look for a special classname
        $class = $this->getAttribute('class');
        if (trim($class)) {
            if (preg_match($this->regexps['negative'], $class)) {
                $weight -= 25;
            }

            if (preg_match($this->regexps['positive'], $class)) {
                $weight += 25;
            }
        }

        // Look for a special ID
        $id = $this->getAttribute('class');
        if (trim($id)) {
            if (preg_match($this->regexps['negative'], $id)) {
                $weight -= 25;
            }

            if (preg_match($this->regexps['positive'], $id)) {
                $weight += 25;
            }
        }

        return $weight;
    }

    /**
     * Returns the current score of the Readability object.
     *
     * @return int
     */
    public function getContentScore()
    {
        return $this->contentScore;
    }

    /**
     * Returns the current score of the Readability object.
     *
     * @param int $score
     *
     * @return int
     */
    public function setContentScore($score)
    {
        // To prevent the -0 value
        $this->contentScore = ($score === (double)-0) ? 0 : $score;

        return $this->contentScore;
    }

    /**
     * Returns the full text of the node.
     *
     * @param bool $normalize Normalize white space?
     *
     * @return string
     */
    public function getTextContent($normalize = false)
    {
        $nodeValue = $this->node->nodeValue;
        if ($normalize) {
            $nodeValue = trim(preg_replace('/\s{2,}/', ' ', $nodeValue));
        }
        return $nodeValue;
    }

    /**
     * Sets the node name
     *
     * @param string $value
     */
    public function setNodeName($value)
    {
        $this->node->nodeName = $value;
    }

    /**
     * Returns the current DOMNode
     *
     * @return \DOMNode
     */
    public function getDOMNode()
    {
        return $this->node;
    }

    public function removeAndGetNext($node)
    {
        $nextNode = $this->getNextNode($node, true);
        $nextNode->node->parentNode->removeChild($node->node);
        return $nextNode;
    }

    public function getNextNode($originalNode, $ignoreSelfAndKids = false)
    {
        /**
         * Traverse the DOM from node to node, starting at the node passed in.
         * Pass true for the second parameter to indicate this node itself
         * (and its kids) are going away, and we want the next node over.
         *
         * Calling this in a loop will traverse the DOM depth-first.
         */

        // First check for kids if those aren't being ignored
        if (!$ignoreSelfAndKids && $originalNode->node->firstChild) {
            return new self($originalNode->node->firstChild);
        }

        // Then for siblings...
        if ($originalNode->node->nextSibling) {
            return new self($originalNode->node->nextSibling);
        }

        // And finally, move up the parent chain *and* find a sibling
        // (because this is depth-first traversal, we will have already
        // seen the parent nodes themselves).
        do {
            $originalNode = $originalNode->getParent();
        } while ($originalNode && !$originalNode->node->nextSibling);

        return ($originalNode) ? new self($originalNode->node->nextSibling) : $originalNode;
    }
}
