# Geben 0.30

## About

This project is a clone of the original geben-on-emacs by fujinaka.tohru, with minimal changes applied to keep geben working. Necessary updates have included:

* support for modified debug protocol header blocks introduced by php unicode files
* support for Emacs evolutionary changes (like obsolete functions or new signatures)

The original README file remains useful if you've never used geben.

At time of writing, the code has been actively used with:

* Emacs 26, 28
* Xdebug 2 and 3.
* PHP `5.*`, `7.*`, `8.*`

## Install

Simplest deployment is to clone under ~/.emacs.d/geben in place of the melpa geben package.

## Setup

Example .emacs setup

```lisp
    (setq load-path (cons "~/.emacs.d/geben" load-path))
    (autoload 'geben "geben" "PHP Debugger on Emacs" t)
    (add-hook 'dbgp-start-hook
       (lambda ()
         (setq dbgp-listener-interface "eth0")
         (setq dbgp-listener-ipv4address [192 168 0 1])))
    (global-set-key (kbd "C-x RET RET") 'geben)
    (global-set-key (kbd "C-x #") 'geben-end)
```
    
## 0.30

* Fix geben ungraceful quit with storage corruption, and unreliable restart (removed pp)

## 0.29

* Code sweep to complete full Emacs 28 compatibility (including decouple all deprecated cl.el)

## 0.28

* Emacs 27 / 28 compatible (removed obsolete function aliases and warnings)
* Support for newer `make-network-process` signature:
  * Added auto detection of local machine ipv4 address (defaults to eth0 interface)
  * Network interface or ip are optionally configurable with a new hook and two new variables.
