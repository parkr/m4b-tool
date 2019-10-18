<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class ContentMetadataJson implements TagImproverInterface
{
    const BOM = "\xEF\xBB\xBF";
    protected $chaptersContent;

    public function __construct($fileContents = "")
    {
        $this->chaptersContent = $fileContents;
    }

    /**
     * Cover constructor.
     * @param SplFileInfo $reference
     * @param null $fileName
     * @return ContentMetadataJson
     */
    public static function fromFile(SplFileInfo $reference, $fileName = null)
    {
        $path = $reference->isDir() ? $reference : new SplFileInfo($reference->getPath());
        $fileName = $fileName ? $fileName : "content_metadata_*.json";


        $globPattern = $path . "/" . $fileName;
        $files = glob($globPattern);
        if (!is_array($files) || count($files) === 0) {
            return new static();
        }

        $fileToLoad = new SplFileInfo($files[0]);
        if ($fileToLoad->isFile()) {
            return new static(static::stripBOM(file_get_contents($fileToLoad)));
        }
        return new static();
    }

    private static function stripBOM($contents)
    {
        if (substr($contents, 0, 3) === static::BOM) {
            return substr($contents, 3);
        }
        return $contents;
    }


    /**
     * @param Tag $tag
     * @return Tag
     */
    public function improve(Tag $tag): Tag
    {
        if (trim($this->chaptersContent) === "") {
            return $tag;
        }
        $decoded = @json_decode($this->chaptersContent, true, 512, JSON_BIGINT_AS_STRING);
        $decodedChapters = $decoded["content_metadata"]["chapter_info"]["chapters"] ?? [];
        if (count($decodedChapters) === 0) {
            return $tag;
        }
        /** @var Chapter[] $chapters */
        $chapters = [];
        if (isset($decoded["content_metadata"]["chapter_info"]["brandIntroDurationMs"])) {
            $chapters[] = new Chapter(new TimeUnit(0), new TimeUnit($decoded["content_metadata"]["chapter_info"]["brandIntroDurationMs"]), "Intro");
        }
        $i = 1;
        foreach ($decodedChapters as $decodedChapter) {
            $lengthMs = $decodedChapter["length_ms"] ?? 0;
            $title = $decodedChapter["title"] ?? $i++;
            $lastKey = count($chapters) - 1;
            $lastChapterEnd = isset($chapters[$lastKey]) ? new TimeUnit($chapters[$lastKey]->getEnd()->milliseconds()) : new TimeUnit();
            $chapters[] = new Chapter($lastChapterEnd, new TimeUnit($lengthMs), $title);
        }

        if (isset($decoded["content_metadata"]["chapter_info"]["brandOutroDurationMs"])) {
            $chapters[] = new Chapter(new TimeUnit(0), new TimeUnit($decoded["content_metadata"]["chapter_info"]["brandOutroDurationMs"]), "Outro");
        }
        $tag->chapters = $chapters;

        $audibleId = $decoded["content_metadata"]["content_reference"]["asin"] ?? null;
        if ($audibleId !== null) {
            $tag->extraProperties["audible_id"] = $audibleId;
        }
        return $tag;
    }
}
