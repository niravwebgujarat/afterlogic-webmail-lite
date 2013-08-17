<?php

/*
 * Copyright (C) 2002-2013 AfterLogic Corp. (www.afterlogic.com)
 * Distributed under the terms of the license described in LICENSE
 *
 */

CApi::Inc('common.net.abstract');

/**
 * @package Api
 * @subpackage Net
 */
class CApiPop3MailProtocol extends CApiNetAbstract
{
	/**
	* @var array
	*/
	protected $aCapa;

	public function __construct($sHost, $iPort, $bUseSsl = false, $iConnectTimeOut = null, $iSocketTimeOut = null)
	{
		parent::__construct($sHost, $iPort, $bUseSsl, $iConnectTimeOut, $iSocketTimeOut);

		$this->aCapa = null;
	}

	/**
	 * @return bool
	 */
	public function Connect()
	{
		$bResult = false;
		if (parent::Connect())
		{
			$bResult = $this->CheckResponse($this->GetNextLine());
		}
		return $bResult;
	}

	/**
	 * @param string $sIncCapa
	 * @param bool $bForce = false
	 * @return bool
	 */
	public function IsSupported($sIncCapa, $bForce = false)
	{
		if (null === $this->aCapa || $bForce)
		{
			if ($this->SendCommand('CAPA'))
			{
				$this->aCapa = array();
				while($sResponse = $this->GetNextLine())
				{
					if (substr($sResponse, 0, 3) == ".\r\n") {
						break;
					}
					$this->aCapa[] = trim($sResponse);
				}
			}
		}

		return is_array($this->aCapa) && in_array($sIncCapa, $this->aCapa);
	}

	/**
	 * @param string $sLogin
	 * @param string $sPassword
	 * @return bool
	 */
	public function Login($sLogin, $sPassword)
	{
		$bTLS = ($this->IsSupported('STLS') && function_exists('stream_socket_enable_crypto'));
		if($bTLS) {
			CApi::Log('POP3 : Connection to '.$this->sHost.':'.$this->iPort.' advertises STLS support. Attempting to secure.');
			if ($this->SendCommand('STLS'))
			{
				@stream_socket_enable_crypto($this->rConnect, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
				CApi::Log('POP3 : Connection secured. Re-requesting server capabilities.');
				$this->IsSupported('STLS', true);
			}
			else
			{
				CApi::Log('POP3 : Unable to secure connection. Will try to continue witout TLS encryption. Error: '.$sResponse, ELogLevel::Warning);
			}
		}

		return $this->SendCommand('USER '.$sLogin) && $this->SendCommand('PASS '.$sPassword, array($sPassword));
	}

	/**
	 * @param string $sLogin
	 * @param string $sPassword
	 * @return bool
	 */
	public function ConnectAndLogin($sLogin, $sPassword)
	{
		return $this->Connect() && $this->Login($sLogin, $sPassword);
	}

	/**
	 * @return bool
	 */
	public function Disconnect()
	{
		return parent::Disconnect();
	}

	/**
	 * @return bool
	 */
	public function Logout()
	{
		return $this->SendCommand('QUIT');
	}

	/**
	 * @return bool
	 */
	public function LogoutAndDisconnect()
	{
		return $this->Logout() && $this->Disconnect();
	}

	/**
	 * @return bool
	 */
	public function GetNamespace()
	{
		return '';
	}

	/**
	 * @param string $sCmd
	 * @return bool
	 */
	public function SendLine($sCmd)
	{
		return $this->WriteLine($sCmd);
	}

	/**
	 * @param string $sCmd
	 * @param array $aHideValues = array()
	 * @return bool
	 */
	public function SendCommand($sCmd, $aHideValues = array())
	{
		if ($this->WriteLine($sCmd, $aHideValues))
		{
			return $this->CheckResponse($this->GetNextLine());
		}

		return false;
	}

	/**
	 * @return string
	 */
	public function GetNextLine()
	{
		return $this->ReadLine();
	}

	/**
	 * @param string $sResponse
	 * @return bool
	 */
	public function CheckResponse($sResponse)
	{
		return ('+OK' === substr($sResponse, 0, 3));
	}
}
