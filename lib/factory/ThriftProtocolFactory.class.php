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
	 * @param array $config
	 * @return TTransport
	 */
	private static function getConnector($config) {
		$class = isset($config['class']) ? $config['class'] : '';
		$param = isset($config['param']) ? $config['param'] : array();
		switch($class) {
			case 'THttpClient':
				if(!isset($param['host'])) throw new Exception ('Bad Thrift transport config');

				$host = $param['host'];
				$port = isset($param['port']) ? $param['port'] : 80;
				$uri = isset($param['uri']) ? $param['uri'] : '';
				$scheme = isset($param['scheme']) ? $param['scheme'] : 'http';
				$timeout = isset($param['timeout']) ? $param['timeout'] : null;

				$transport = new THttpClient($url, $port, $uri, $scheme);
				$transport->setTimeoutSecs($timeout);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create THttpClient with parameters: host = "%s", port = %d, uri = "%s", scheme = "%s", timeout = %d', $host, $port, $uri, $scheme, $timeout));
				break;
			case 'TMemoryBuffer':
				$buf = isset($param['buf']) ? $param['buf'] : '';

				$transport = new TMemoryBuffer($buf);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TMemoryBuffer with parameters: buf = "%s"', $buf));
				break;
			case 'TPhpStream':
				if(!isset($param['mode'])) throw new Exception ('Bad Thrift transport config');

				$mode = $param['mode'];

				$transport = new TPhpStream($mode);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TPhpStream with parameters: mode = %d', $mode));
				break;
			case 'TServerSocket':
				$host = isset($param['host']) ? $param['host'] : 'localhost';
				$port = isset($param['port']) ? $param['port'] : 9090;

				$transport = new TServerSocket($host, $port);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TServerSocket with parameters: host = "%s"', $host, $port));
				break;
			case 'TSocket':
				$host = isset($param['host']) ? $param['host'] : 'localhost';
				$port = isset($param['port']) ? $param['port'] : 9090;
				$persist = isset($param['persist']) ? $param['persist'] : false;
				$send_timeout = isset($param['send_timeout']) ? $param['send_timeout'] : 100;
				$recv_timeout = isset($param['recv_timeout']) ? $param['recv_timeout'] : 750;

				$transport = new TSocket($host, $port, $persist);
				$transport->setSendTimeout($send_timeout);
				$transport->setRecvTimeout($recv_timeout);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TSocket with parameters: host = "%s", port = %d, persist = %s, send_timeout = %d, recv_timeout = %d', $host, $port, $persist ? 'true' : 'false', $send_timeout, $recv_timeout));
				break;
			case 'TSocketPool':
				$hosts = isset($param['hosts']) ? $param['hosts'] : array('localhost');
				$ports = isset($param['ports']) ? $param['ports'] : array(9090);
				$persist = isset($param['persist']) ? $param['persist'] : false;
				$send_timeout = isset($param['send_timeout']) ? $param['send_timeout'] : 100;
				$recv_timeout = isset($param['recv_timeout']) ? $param['recv_timeout'] : 750;

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

				$connector = new TBufferedTransport($connector, $rBufSize, $wBufSize);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TBufferedTransport with parameters: read_buf_size = %d, write_buf_size = %d', $rBufSize, $wBufSize));
				break;
			case 'TFramedTransport':
				$read = isset($param['read']) ? $param['read'] : true;
				$write = isset($param['write']) ? $param['write'] : true;

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

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TBinaryProtocol with parameters: strictRead = %s, strictWrite = %s', $strictRead ? 'true' : 'false', $strictWrite ? 'true' : 'false'));
				break;
			case 'TBinaryProtocolAccelerated':
				$strictRead = isset($param['strict_read']) ? $param['strict_read'] : false;
				$strictWrite = isset($param['strict_write']) ? $param['strict_write'] : true;

				$protocol = new TBinaryProtocolAccelerated($transport, $strictRead, $strictWrite);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TBinaryProtocolAccelerated with parameters: strictRead = %s, strictWrite = %s', $strictRead ? 'true' : 'false', $strictWrite ? 'true' : 'false'));
				break;
			default:
				throw new Exception('Unknown protocol: '.$class);
		}

		return $protocol;
	}
}