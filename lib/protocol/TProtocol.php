<?php
/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements. See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership. The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 * @package thrift.protocol
 */


/**
 * Protocol module. Contains all the types and definitions needed to implement
 * a protocol encoder/decoder.
 *
 * @package thrift.protocol
 */

/**
 * Protocol base class module.
 */
abstract class TProtocol {
  // The below may seem silly, but it is to get around the problem that the
  // "instanceof" operator can only take in a T_VARIABLE and not a T_STRING
  // or T_CONSTANT_ENCAPSED_STRING. Using "is_a()" instead of "instanceof" is
  // a workaround but is deprecated in PHP5. This is used in the generated
  // deserialization code.
  static $TBINARYPROTOCOLACCELERATED = 'TBinaryProtocolAccelerated';

  /**
   * Underlying transport
   *
   * @var TTransport
   */
  protected $trans_;

  /**
   * Constructor
   */
  protected function __construct($trans) {
    $this->trans_ = $trans;
  }

  /**
   * Accessor for transport
   *
   * @return TTransport
   */
  public function getTransport() {
    return $this->trans_;
  }

  /**
   * Writes the message header
   *
   * @param string $name Function name
   * @param int $type message type TMessageType::CALL or TMessageType::REPLY
   * @param int $seqid The sequence id of this message
   */
  public abstract function writeMessageBegin($name, $type, $seqid);

  /**
   * Close the message
   */
  public abstract function writeMessageEnd();

  /**
   * Writes a struct header.
   *
   * @param string     $name Struct name
   * @throws TException on write error
   * @return int How many bytes written
   */
  public abstract function writeStructBegin($name);

  /**
   * Close a struct.
   *
   * @throws TException on write error
   * @return int How many bytes written
   */
  public abstract function writeStructEnd();

  /*
   * Starts a field.
   *
   * @param string     $name Field name
   * @param int        $type Field type
   * @param int        $fid  Field id
   * @throws TException on write error
   * @return int How many bytes written
   */
  public abstract function writeFieldBegin($fieldName, $fieldType, $fieldId);

  public abstract function writeFieldEnd();

  public abstract function writeFieldStop();

  public abstract function writeMapBegin($keyType, $valType, $size);

  public abstract function writeMapEnd();

  public abstract function writeListBegin($elemType, $size);

  public abstract function writeListEnd();

  public abstract function writeSetBegin($elemType, $size);

  public abstract function writeSetEnd();

  public abstract function writeBool($bool);

  public abstract function writeByte($byte);

  public abstract function writeI16($i16);

  public abstract function writeI32($i32);

  public abstract function writeI64($i64);

  public abstract function writeDouble($dub);

  public abstract function writeString($str);

  /**
   * Reads the message header
   *
   * @param string $name Function name
   * @param int $type message type TMessageType::CALL or TMessageType::REPLY
   * @parem int $seqid The sequence id of this message
   */
  public abstract function readMessageBegin(&$name, &$type, &$seqid);

  /**
   * Read the close of message
   */
  public abstract function readMessageEnd();

  public abstract function readStructBegin(&$name);

  public abstract function readStructEnd();

  public abstract function readFieldBegin(&$name, &$fieldType, &$fieldId);

  public abstract function readFieldEnd();

  public abstract function readMapBegin(&$keyType, &$valType, &$size);

  public abstract function readMapEnd();

  public abstract function readListBegin(&$elemType, &$size);

  public abstract function readListEnd();

  public abstract function readSetBegin(&$elemType, &$size);

  public abstract function readSetEnd();

  public abstract function readBool(&$bool);

  public abstract function readByte(&$byte);

  public abstract function readI16(&$i16);

  public abstract function readI32(&$i32);

  public abstract function readI64(&$i64);

  public abstract function readDouble(&$dub);

  public abstract function readString(&$str);

  /**
   * The skip function is a utility to parse over unrecognized date without
   * causing corruption.
   *
   * @param TType $type What type is it
   */
  public function skip($type) {
    switch ($type) {
    case TType::BOOL:
      return $this->readBool($bool);
    case TType::BYTE:
      return $this->readByte($byte);
    case TType::I16:
      return $this->readI16($i16);
    case TType::I32:
      return $this->readI32($i32);
    case TType::I64:
      return $this->readI64($i64);
    case TType::DOUBLE:
      return $this->readDouble($dub);
    case TType::STRING:
      return $this->readString($str);
    case TType::STRUCT:
      {
        $result = $this->readStructBegin($name);
        while (true) {
          $result += $this->readFieldBegin($name, $ftype, $fid);
          if ($ftype == TType::STOP) {
            break;
          }
          $result += $this->skip($ftype);
          $result += $this->readFieldEnd();
        }
        $result += $this->readStructEnd();
        return $result;
      }
    case TType::MAP:
      {
        $result = $this->readMapBegin($keyType, $valType, $size);
        for ($i = 0; $i < $size; $i++) {
          $result += $this->skip($keyType);
          $result += $this->skip($valType);
        }
        $result += $this->readMapEnd();
        return $result;
      }
    case TType::SET:
      {
        $result = $this->readSetBegin($elemType, $size);
        for ($i = 0; $i < $size; $i++) {
          $result += $this->skip($elemType);
        }
        $result += $this->readSetEnd();
        return $result;
      }
    case TType::LST:
      {
        $result = $this->readListBegin($elemType, $size);
        for ($i = 0; $i < $size; $i++) {
          $result += $this->skip($elemType);
        }
        $result += $this->readListEnd();
        return $result;
      }
    default:
      return 0;
    }
  }

  /**
   * Utility for skipping binary data
   *
   * @param TTransport $itrans TTransport object
   * @param int        $type   Field type
   */
  public static function skipBinary($itrans, $type) {
    switch ($type) {
    case TType::BOOL:
      return $itrans->readAll(1);
    case TType::BYTE:
      return $itrans->readAll(1);
    case TType::I16:
      return $itrans->readAll(2);
    case TType::I32:
      return $itrans->readAll(4);
    case TType::I64:
      return $itrans->readAll(8);
    case TType::DOUBLE:
      return $itrans->readAll(8);
    case TType::STRING:
      $len = unpack('N', $itrans->readAll(4));
      $len = $len[1];
      if ($len > 0x7fffffff) {
        $len = 0 - (($len - 1) ^ 0xffffffff);
      }
      return 4 + $itrans->readAll($len);
    case TType::STRUCT:
      {
        $result = 0;
        while (true) {
          $ftype = 0;
          $fid = 0;
          $data = $itrans->readAll(1);
          $arr = unpack('c', $data);
          $ftype = $arr[1];
          if ($ftype == TType::STOP) {
            break;
          }
          // I16 field id
          $result += $itrans->readAll(2);
          $result += self::skipBinary($itrans, $ftype);
        }
        return $result;
      }
    case TType::MAP:
      {
        // Ktype
        $data = $itrans->readAll(1);
        $arr = unpack('c', $data);
        $ktype = $arr[1];
        // Vtype
        $data = $itrans->readAll(1);
        $arr = unpack('c', $data);
        $vtype = $arr[1];
        // Size
        $data = $itrans->readAll(4);
        $arr = unpack('N', $data);
        $size = $arr[1];
        if ($size > 0x7fffffff) {
          $size = 0 - (($size - 1) ^ 0xffffffff);
        }
        $result = 6;
        for ($i = 0; $i < $size; $i++) {
          $result += self::skipBinary($itrans, $ktype);
          $result += self::skipBinary($itrans, $vtype);
        }
        return $result;
      }
    case TType::SET:
    case TType::LST:
      {
        // Vtype
        $data = $itrans->readAll(1);
        $arr = unpack('c', $data);
        $vtype = $arr[1];
        // Size
        $data = $itrans->readAll(4);
        $arr = unpack('N', $data);
        $size = $arr[1];
        if ($size > 0x7fffffff) {
          $size = 0 - (($size - 1) ^ 0xffffffff);
        }
        $result = 5;
        for ($i = 0; $i < $size; $i++) {
          $result += self::skipBinary($itrans, $vtype);
        }
        return $result;
      }
    default:
      return 0;
    }
  }
}
