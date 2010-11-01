<?php
/**
 * Description of ThriftProtocolFactory
 *
 * @author Tomasz Jakub Rup <tomasz.rup@gmail.com>
 */
final class ThriftProtocolFactory {
	/**
	 *
	 * @param string $configPostfix Config postfix
	 * @return TProtocol
	 */
	public static final function factory($configPostfix = 'default') {
		$config = sfConfig::get('app_thrift_plugin_'.$configPostfix);
		if(
			empty($config['connector']) ||
			empty($config['transport']) ||
			empty($config['protocol'])
		) {
			throw new Exception('Bad Thrift config');
		}

		$connector = self::getConnector($config['connector']);
		$connector->open();
		$transport = self::getTransport($config['transport'], $connector);

		return self::getProtocol($config['protocol'], $transport);
	}

	/**
	 *
	 * @param array $params
	 * @return TTransport
	 */
	private static function getConnector($config) {
		$class = isset($config['class']) ? $config['class'] : '';
		$params = isset($config['params']) ? $config['params'] : array();
		switch($class) {
			case 'THttpClient':
				if(!isset($params['host'])) throw new Exception ('Bad Thrift transport config');

				$host = $params['host'];
				$port = isset($params['port']) ? $params['port'] : 80;
				$uri = isset($params['uri']) ? $params['uri'] : '';
				$scheme = isset($params['scheme']) ? $params['scheme'] : 'http';
				$timeout = isset($params['timeout']) ? $params['timeout'] : null;

				$transport = new THttpClient($url, $port, $uri, $scheme);
				$transport->setTimeoutSecs($timeout);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create THttpClient with parameters: host = "%s", port = %d, uri = "%s", scheme = "%s", timeout = %d', $host, $port, $uri, $scheme, $timeout));
				break;
			case 'TMemoryBuffer':
				$buf = isset($params['buf']) ? $params['buf'] : '';

				$transport = new TMemoryBuffer($buf);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TMemoryBuffer with parameters: buf = "%s"', $buf));
				break;
			case 'TPhpStream':
				if(!isset($params['mode'])) throw new Exception ('Bad Thrift transport config');

				$mode = $params['mode'];

				$transport = new TPhpStream($mode);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TPhpStream with parameters: mode = %d', $mode));
				break;
			case 'TServerSocket':
				$host = isset($params['host']) ? $params['host'] : 'localhost';
				$port = isset($params['port']) ? $params['port'] : 9090;

				$transport = new TServerSocket($host, $port);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TServerSocket with parameters: host = "%s"', $host, $port));
				break;
			case 'TSocket':
				$host = isset($params['host']) ? $params['host'] : 'localhost';
				$port = isset($params['port']) ? $params['port'] : 9090;
				$persist = isset($params['persist']) ? $params['persist'] : false;
				$send_timeout = isset($params['send_timeout']) ? $params['send_timeout'] : 100;
				$recv_timeout = isset($params['recv_timeout']) ? $params['recv_timeout'] : 750;

				$transport = new TSocket($host, $port, $persist);
				$transport->setSendTimeout($send_timeout);
				$transport->setRecvTimeout($recv_timeout);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TSocket with parameters: host = "%s", port = %d, persist = %s, send_timeout = %d, recv_timeout = %d', $host, $port, $persist ? 'true' : 'false', $send_timeout, $recv_timeout));
				break;
			case 'TSocketPool':
				$hosts = isset($params['hosts']) ? $params['hosts'] : array('localhost');
				$ports = isset($params['ports']) ? $params['ports'] : array(9090);
				$persist = isset($params['persist']) ? $params['persist'] : false;
				$send_timeout = isset($params['send_timeout']) ? $params['send_timeout'] : 100;
				$recv_timeout = isset($params['recv_timeout']) ? $params['recv_timeout'] : 750;

				$transport = new TSocket($host, $port, $persist);
				$transport->setSendTimeout($send_timeout);
				$transport->setRecvTimeout($recv_timeout);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TSocketPool with parameters: hosts = ("%s"), ports = (%d), persist = %s, send_timeout = %d, recv_timeout = %d', implode('","', $hosts), implode('","', $ports), $persist ? 'true' : 'false', $send_timeout, $recv_timeout));
				break;
			default:
				throw new Exception('Unknown transport: '.$class);
		}

		return $transport;
	}

	/**
	 *
	 * @param array $params
	 * @param TTransport $connector
	 * @return TTransport
	 */
	private static function getTransport($config, TTransport $connector) {
		$class = isset($config['class']) ? $config['class'] : '';
		$params = isset($config['params']) ? $config['params'] : array();
		switch($class) {
			case 'TBufferedTransport':
				$rBufSize = isset($params['read_buf_size']) ? $params['read_buf_size'] : 512;
				$wBufSize = isset($params['write_buf_size']) ? $params['write_buf_size'] : 512;

				$connector = new TBufferedTransport($connector, $rBufSize, $wBufSize);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TBufferedTransport with parameters: read_buf_size = %d, write_buf_size = %d', $rBufSize, $wBufSize));
				break;
			case 'TFramedTransport':
				$read = isset($params['read']) ? $params['read'] : true;
				$write = isset($params['write']) ? $params['write'] : true;

				$connector = new TFramedTransport($connector, $read, $write);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TFramedTransport with parameters: read = %s, write = %s', $read ? 'true' : 'false', $write ? 'true' : 'false'));
				break;
			case 'TNullTransport':
				$connector = new TNullTransport();

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TNullTransport'));
				break;
			default:
				throw new Exception('Unknown transport: '.$class);
		}

		return $connector;
	}

	/**
	 *
	 * @param array $params
	 * @param TTransport
	 * @return TProtocol
	 */
	private static function getProtocol($config, TTransport $transport) {
		$class = isset($config['class']) ? $config['class'] : '';
		$params = isset($config['params']) ? $config['params'] : array();
		switch($class) {
			case 'TBinaryProtocol':
				$strictRead = isset($params['strict_read']) ? $params['strict_read'] : false;
				$strictWrite = isset($params['strict_write']) ? $params['strict_write'] : true;

				$protocol = new TBinaryProtocol($transport, $strictRead, $strictWrite);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TBinaryProtocol with parameters: strictRead = %s, strictWrite = %s', $strictRead ? 'true' : 'false', $strictWrite ? 'true' : 'false'));
				break;
			case 'TBinaryProtocolAccelerated':
				$strictRead = isset($params['strict_read']) ? $params['strict_read'] : false;
				$strictWrite = isset($params['strict_write']) ? $params['strict_write'] : true;

				$protocol = new TBinaryProtocolAccelerated($transport, $strictRead, $strictWrite);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TBinaryProtocolAccelerated with parameters: strictRead = %s, strictWrite = %s', $strictRead ? 'true' : 'false', $strictWrite ? 'true' : 'false'));
				break;
			default:
				throw new Exception('Unknown protocol: '.$class);
		}

		return $protocol;
	}
}