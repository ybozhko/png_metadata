<?php

$file = 'test.png';

$png = new PNG_MetaDataHandler($file);

$assertion = '{
   "recipient":"XXX@gmail.com",
   "evidence":"/whatever.html",
   "expires":"2040-08-13",
   "issued_on":"2011-08-23",
   "badge":{
      "version":"v0.5.0",
      "name":"Badge 1427691567831-1",
      "description":"For rocking in the free world",
      "image":"http://vanillicon.com/cbeb7a6a9fecb4b9eca6f389b6b1fdd9.png",
      "criteria":"/criteria.html",
      "issuer":{
         "origin":"http://badgetest.herokuapp.com",
         "name":"yep",
         "contact":"admin@sup.org"
      }
   },
   "verify":{
      "type":"hosted",
      "url":"http://badgetest.herokuapp.com/hashed.json?email=XXX%40gmail.com&override=%7B%22badge%22%3A%7B%22issuer%22%3A%7B%22origin%22%3A%22http%3A%2F%2Fbadgetest.herokuapp.com%22%7D%2C%22name%22%3A%22Badge%201427691567831-1%22%2C%22image%22%3A%22http%3A%2F%2Fvanillicon.com%2Fcbeb7a6a9fecb4b9eca6f389b6b1fdd9.png%22%7D%7D"
   }
}';

if ($png->check_chunks("iTXt", "openbadges")) {
        $png->write_chunks("iTXt", "openbadges", $assertion);
    }

$png->print_chunks('iTXt');

class PNG_MetaDataHandler
{
    /** @var string File content as a string */
    private $_contents;
    /** @var int Length of the image file */
    private $_size;
    /** @var array Variable for storing parsed chunks */
    private $_chunks;

    /**
     * Prepares file for handling metadata.
     * Verifies that this file is a valid PNG file.
     * Unpacks file chunks and reads them into an array.
     *
     * @param string $contents File content as a string
     */
    function __construct($file) {
        if (!file_exists($file))
            throw new Exception('File does not exist');

        if (!$this->_contents = file_get_contents($file))
            throw new Exception('Unable to open file');

        $png_signature = pack("C8", 137, 80, 78, 71, 13, 10, 26, 10);

        // Read 8 bytes of PNG header and verify
        $header = substr($this->_contents, 0, 8);

        if ($header != $png_signature)
            throw new Exception('This is not a valid PNG image');

        $this->_size = strlen($this->_contents);

        $this->_chunks = array();

        //Skip 8 bytes of header
        $position = 8;
        do {
            $chunk = @unpack('Nsize/a4type', substr($this->_contents, $position, 8));
            $this->_chunks[$chunk['type']][] = substr($this->_contents, $position + 8, $chunk['size']);

            //Skip 12 bytes chunk overhead
            $position += $chunk['size'] + 12;
        } while ($position < $this->_size);
        var_dump($this->_chunks);
    }

    /**
     * Checks if a key already exists in the chunk of said type.
     * We need to avoid writing same keyword into file chunks.
     *
     * @param string $type Chunk type, like iTXt, tEXt, etc.
     * @param string $check Keyword that needs to be checked.
     *
     * @return boolean (true|false) True if file is safe to write this keyword, false otherwise.
     */
    public function check_chunks($type, $check) {
        if (array_key_exists($type, $this->_chunks)) {
            foreach (array_keys($this->_chunks[$type]) as $typekey) {
                list($key, $data) = explode("\0", $this->_chunks[$type][$typekey]);

                if (strcmp($key, $check) == 0) {
                    echo "Key '" . $check . "' already exists in '" . $type . "' chunk.";
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Adds a chunk with keyword and data to the file content.
     * Chunk is added to the end of the file, before IEND image trailer.
     *
     * @param string $type Chunk type, like iTXt, tEXt, etc.
     * @param string $key Keyword that needs to be added.
     * @param string $value Currently an assertion URL that is added to an image metadata.
     */
    public function write_chunks($type, $key, $value) {
        if (strlen($key) > 79)
            throw new Exception('Key is too big');

        if ($type == 'iTXt') {
            // iTXt International textual data
            // Keyword:             1-79 bytes (character string)
            // Null separator:      1 byte
            // Compression flag:    1 byte
            // Compression method:  1 byte
            // Language tag:        0 or more bytes (character string)
            // Null separator:      1 byte
            // Translated keyword:  0 or more bytes
            // Null separator:      1 byte
            // Text:                0 or more bytes
            $data = $key . "\x00\x00\x00\x00\x00" . $value;
        } else {
            // tEXt Textual data
            // Keyword:        1-79 bytes (character string)
            // Null separator: 1 byte
            // Text:           n bytes (character string)
            $data = $key . "\0" . $value;
        }
        $crc = pack("N", crc32($type . $data));
        $len = pack("N", strlen($data));

        //Chunk format: length + type + data + CRC
        //CRC is a CRC-32 computed over the chunk type and chunk data
        $newchunk = $len . $type . $data . $crc;

        $result = file_put_contents('test.png', substr($this->_contents, 0, $this->_size - 12)
                                        . $newchunk
                                        . substr($this->_contents, $this->_size - 12, 12));

        if ($result !== false) {
            $this->_chunks[$type] = $data;
        } else {
            echo "Unable to write to file. <br/>";
        }
    }

    /**
     * Prints chunks of a specific type.
     */
    public function print_chunks($type) {
        if (array_key_exists($type, $this->_chunks)) {
            var_dump($this->_chunks[$type]);
        } else {
            echo "There is no '". $type . "' chunk type.";
        }
    }
}
?>
