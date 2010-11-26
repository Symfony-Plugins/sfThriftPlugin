<?php
/**
 * Helper class for making a TProtocol object.
 *
 * @author Tomasz Jakub Rup <tomasz.rup@gmail.com>
 * @package Thrift
 * @subpackage factory
 * @final
 */
final class ThriftProtocolFactory {

	/**
	 * Create a TProtocol object for specific config params.
	 * @param string $profile Config profile
	 * @return TProtocol TProtocol object
	 * @final
	 */
	public static final function factory($profile = 'default') {
		$config = sfConfig::get('app_thrift_plugin_'.$profile);
		if(
			empty($config['connector']) ||
			empty($config['transport']) ||
			empty($config['protocol'])
		) {
			throw new Exception("Bad Thrift $profile config");
		}

		$connector = self::getConnector($config['connector']);
		$connector->open();
		$transport = self::getTransport($config['transport'], $connector);

		return self::getProtocol($config['protocol'], $transport);
	}

	/**
	 * Create connector object
	 * @param array $config
	 * @return TTransport
	 */
	private static function getConnector($config) {
		$class = isset($config['class']) ? $config['class'] : '';
		$param = isset($config['param']) ? $config['param'] : array();
		switch($class) {
			case 'THttpClient':
				if(!isset($param['host']))
					throw new Exception ('Bad Thrift transport config');

				$host = $param['host'];
				$port = isset($param['port']) ? $param['port'] : 80;
				$uri = isset($param['uri']) ? $param['uri'] : '';
				$scheme = isset($param['scheme']) ? $param['scheme'] : 'http';
				$timeout = isset($param['timeout']) ? $param['timeout'] : null;

				$connector = new THttpClient($url, $port, $uri, $scheme);
				$connector->setTimeoutSecs($timeout);

				$parameters = sprintf(
					'host = "%s", port = %d, uri = "%s", scheme = "%s", timeout = %d',
					$host, $port, $uri, $scheme, $timeout
				);
				break;
			case 'TMemoryBuffer':
				$buf = isset($param['buf']) ? $param['buf'] : '';

				$connector = new TMemoryBuffer($buf);

				$parameters = sprintf('buf = "%s"', $buf);
				break;
			case 'TPhpStream':
				if(!isset($param['mode']))
					throw new Exception ('Bad Thrift transport config');

				$mode = $param['mode'];

				$connector = new TPhpStream($mode);

				$parameters = sprintf('mode = %d', $mode);
				break;
			case 'TSocket':
				$host = isset($param['host']) ? $param['host'] : 'localhost';
				$port = isset($param['port']) ? $param['port'] : 9090;
				$persist = isset($param['persist']) ? $param['persist'] : false;
				$send_timeout = isset($param['send_timeout']) ? $param['send_timeout'] : 100;
				$recv_timeout = isset($param['recv_timeout']) ? $param['recv_timeout'] : 750;

				$connector = new TSocket($host, $port, $persist);
				$connector->setSendTimeout($send_timeout);
				$connector->setRecvTimeout($recv_timeout);

				$parameters = sprintf(
					'host = "%s", port = %d, persist = %s, send_timeout = %d, recv_timeout = %d',
					$host, $port, $persist ? 'true' : 'false', $send_timeout, $recv_timeout
				);
				break;
			case 'TSocketPool':
				$hosts = isset($param['hosts']) ? $param['hosts'] : array('localhost');
				$ports = isset($param['ports']) ? $param['ports'] : array(9090);
				$persist = isset($param['persist']) ? $param['persist'] : false;
				$send_timeout = isset($param['send_timeout']) ? $param['send_timeout'] : 100;
				$recv_timeout = isset($param['recv_timeout']) ? $param['recv_timeout'] : 750;

				$connector = new TSocketPool($hosts, $ports, $persist);
				$connector->setSendTimeout($send_timeout);
				$connector->setRecvTimeout($recv_timeout);

				$parameters = sprintf(
					'hosts = ("%s"), ports = (%d), persist = %s, send_timeout = %d, recv_timeout = %d',
					implode('","', $hosts), implode('","', $ports),
					$persist ? 'true' : 'false', $send_timeout, $recv_timeout
				);
				break;
			default:
				throw new Exception('Unknown connector: '.$class);
		}

		sfContext::getInstance()->getLogger()->info(sprintf(
			'{sfThriftPlugin}Create %s connector with parameters: %s', $class, $parameters
		));

		return $connector;
	}

	/**
	 * Create transport object
	 * @param array $config
	 * @param TTransport $connector
	 * @return TTransport
	 */
	private static function getTransport($config, TTransport $connector) {
		$class = isset($config['class']) ? $config['class'] : '';
		$param = isset($config['param']) ? $config['param'] : array();
		switch($class) {
			case 'TBufferedTransport':
				$rBufSize = isset($param['read_buf_size']) ? $param['read_buf_size'] : 512;
				$wBufSize = isset($param['write_buf_size']) ? $param['write_buf_size'] : 512;

				$transport = new TBufferedTransport($connector, $rBufSize, $wBufSize);

				$parameters = sprintf(
					'read_buf_size = %d, write_buf_size = %d', $rBufSize, $wBufSize
				);
				break;
			case 'TFramedTransport':
				$read = isset($param['read']) ? $param['read'] : true;
				$write = isset($param['write']) ? $param['write'] : true;

				$transport = new TFramedTransport($connector, $read, $write);

				$parameters = sprintf(
					'read = %s, write = %s', $read ? 'true' : 'false',
					$write ? 'true' : 'false'
				);
				break;
			case 'TNullTransport':
				$transport = new TNullTransport();

				$parameters = '';
				break;
			default:
				throw new Exception('Unknown transport: '.$class);
		}

		sfContext::getInstance()->getLogger()->info(sprintf(
			'{sfThriftPlugin}Create %s transport with parameters: %s', $class, $parameters
		));

		return $transport;
	}

	/**
	 * Create protocol object
	 * @param array $config
	 * @param TTransport $transport
	 * @return TProtocol
	 */
	private static function getProtocol($config, TTransport $transport) {
		$class = isset($config['class']) ? $config['class'] : '';
		$param = isset($config['param']) ? $config['param'] : array();
		switch($class) {
			case 'TBinaryProtocol':
				$strictRead = isset($param['strict_read']) ? $param['strict_read'] : false;
				$strictWrite = isset($param['strict_write']) ? $param['strict_write'] : true;

				$protocol = new TBinaryProtocol($transport, $strictRead, $strictWrite);

				$parameters = sprintf(
					'strictRead = %s, strictWrite = %s', $strictRead ? 'true' : 'false',
					$strictWrite ? 'true' : 'false'
				);
				break;
			case 'TBinaryProtocolAccelerated':
				$strictRead = isset($param['strict_read']) ? $param['strict_read'] : false;
				$strictWrite = isset($param['strict_write']) ? $param['strict_write'] : true;

				$protocol = new TBinaryProtocolAccelerated($transport, $strictRead, $strictWrite);

				$parameters = sprintf(
					'strictRead = %s, strictWrite = %s', $strictRead ? 'true' : 'false',
					$strictWrite ? 'true' : 'false'
				);
				break;
			default:
				throw new Exception('Unknown protocol: '.$class);
		}

		sfContext::getInstance()->getLogger()->info(sprintf(
			'{sfThriftPlugin}Create %s protocol with parameters: %s', $class, $parameters
		));

		return $protocol;
	}

}
